<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\StartChat;
use App\Enums\ChatMessageKind;
use App\Enums\CloseReason;
use App\Enums\PaymentStatus;
use App\Models\Auction;
use App\Models\AuctionPayment;
use App\Models\Bid;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A closed auction that is ready to be chatted about.
 *
 * Rooms are lazy by design — one only exists once somebody opens it — which
 * makes the payment flow awkward to look at on a fresh database: there is no
 * thread to click into until an auction has closed *and* has a winner. This
 * seeds exactly that state, so the group thread and the credential thread are
 * both already there and the only thing left to do is open the chat.
 *
 * Run with `php artisan db:seed --class=AuctionChatSeeder`, or plain
 * `db:seed` to get it alongside `UserSeeder`.
 */
class AuctionChatSeeder extends Seeder
{
    /**
     * The password shared by every seeded local account.
     */
    private const PASSWORD = 'password';

    /**
     * @var array<string, array{name: string, email: string}>
     */
    private const ACCOUNTS = [
        'admin' => ['name' => 'Admin Biwrabid', 'email' => 'admin@biwrabid.test'],
        'seller' => ['name' => 'Budi Santoso', 'email' => 'budi@biwrabid.test'],
        'winner' => ['name' => 'Siti Aminah', 'email' => 'siti@biwrabid.test'],
        'bidder' => ['name' => 'Rizky Pratama', 'email' => 'rizky@biwrabid.test'],
    ];

    public function run(): void
    {
        // The QRIS and the receipt are real files on the proofs disk, so the
        // seeded thread shows the same attachments a live one would rather than
        // rows pointing at paths that do not exist.
        $disk = Storage::disk(config('chat.proofs.disk'));
        $directory = trim((string) config('chat.proofs.directory'), '/');
        $disk->deleteDirectory($directory);

        $accounts = $this->accounts();
        $auction = $this->endedAuctionWithWinner($accounts);

        $startChat = app(StartChat::class);

        $credentialRoom = $startChat->forAuctionCredentials($auction);
        $groupRoom = $startChat->forAuctionGroup($auction);

        if ($credentialRoom === null || $groupRoom === null) {
            $this->command->error('Expected both auction rooms for an auction with a winner.');

            return;
        }

        $this->credentialThread($credentialRoom, $accounts);
        $this->groupThread($groupRoom, $accounts, $auction, $disk, $directory);
    }

    /**
     * Re-create the demo accounts so the seeder is idempotent.
     *
     * @return array{admin: User, seller: User, winner: User, bidder: User}
     */
    private function accounts(): array
    {
        User::query()->whereIn('email', array_column(self::ACCOUNTS, 'email'))->delete();

        $users = [];

        foreach (self::ACCOUNTS as $key => $account) {
            $users[$key] = User::factory()
                ->when($key === 'admin', fn ($factory) => $factory->admin())
                ->create([...$account, 'password' => self::PASSWORD]);
        }

        return $users;
    }

    /**
     * An auction that has closed, been won, and is waiting for its money.
     *
     * Built out of real rows — a losing bid, a winning bid, an
     * `auction_payments` entry — so the invoice total on screen comes from the
     * same calculation a live auction would use instead of being hardcoded here.
     *
     * @param  array{admin: User, seller: User, winner: User, bidder: User}  $accounts
     */
    private function endedAuctionWithWinner(array $accounts): Auction
    {
        $auction = Auction::factory()->ended()->for($accounts['seller'], 'seller')->create([
            'title' => 'Akun Mobile Legends — Gold Rank',
            'description' => 'Akun Mobile Legends diamond/gold dengan hero pilihan. Skin terbatas. Transaksi aman lewat room ini.',
            'starting_price' => 4_500_000,
            'winner_id' => $accounts['winner']->id,
            'current_price' => 5_750_000,
            'ended_at' => now()->subDay(),
            'ended_reason' => CloseReason::TimeExpired,
        ]);

        // The losing bid comes first, so the winning one has to actually win.
        Bid::factory()
            ->for($auction, 'auction')
            ->for($accounts['bidder'], 'bidder')
            ->create(['amount' => 5_200_000, 'created_at' => now()->subHours(30)]);

        Bid::factory()
            ->for($auction, 'auction')
            ->for($accounts['winner'], 'bidder')
            ->create(['amount' => 5_750_000, 'created_at' => now()->subHours(26)]);

        $auction->refresh();

        AuctionPayment::query()->create([
            'auction_id' => $auction->id,
            'amount' => $auction->paymentAmount() ?? 5_750_000,
            'status' => PaymentStatus::Requested,
        ]);

        return $auction;
    }

    /**
     * The private seller <-> winner thread: no admin, no money.
     *
     * @param  array{admin: User, seller: User, winner: User, bidder: User}  $accounts
     */
    private function credentialThread(ChatRoom $room, array $accounts): void
    {
        $this->message($room, $accounts['seller'], 'Halo, terima kasih sudah menang lelang ini. Ini detail akun ML-nya ya.', [], now()->subHours(21));

        $this->message($room, $accounts['winner'], 'Terima kasih. User ID + server sudah aman ya, bisa dicek.', [], now()->subHours(21)->subMinutes(4));
    }

    /**
     * The admin-visible thread, stopped right before the money moves.
     *
     * The admin has sent the invoice and the winner has paid and uploaded the
     * receipt, but nobody has confirmed anything yet — which is the state worth
     * clicking around in, because every remaining button is a real one.
     *
     * @param  array{admin: User, seller: User, winner: User, bidder: User}  $accounts
     */
    private function groupThread(ChatRoom $room, array $accounts, Auction $auction, Filesystem $disk, string $directory): void
    {
        $payment = AuctionPayment::query()
            ->where('auction_id', $auction->id)
            ->firstOrFail();

        $this->message($room, $accounts['seller'], 'Lelang sudah selesai. Monggo admins bantu proses pembayarannya ya.', [], now()->subHours(22));
        $this->message($room, $accounts['admin'], 'Baik, saya bantu proses di thread ini ya.', [], now()->subHours(21)->subMinutes(30));

        $this->message($room, $accounts['admin'], 'Permintaan pembayaran dikirim. Pemenang, silakan transfer total di atas ke rekening admin.', [
            'kind' => ChatMessageKind::PaymentRequest,
            'amount' => $payment->amount,
            'qris_path' => $this->writeImage($disk, $directory, 'seed-qris-'.$auction->id.'.png'),
        ], now()->subHours(20));

        $this->message($room, $accounts['winner'], 'Bukti transfer ke rekening admin telah diunggah.', [
            'kind' => ChatMessageKind::PaymentProof,
            'proof_path' => $this->writeImage($disk, $directory, 'seed-proof-'.$auction->id.'.png'),
        ], now()->subHours(19));

        $this->message($room, $accounts['winner'], 'Sudah transfer ya, mohon dicek. Terima kasih!', [], now()->subHours(19)->subMinutes(2));

        $this->command->info('Group thread ready. Sign in as admin@biwrabid.test to confirm the funds.');
    }

    /**
     * Seed one message, backdated.
     *
     * The factory is used rather than `$room->messages()->create()` because
     * `created_at` is not fillable, and a thread where every message landed in
     * the same second does not look like the thing being demoed.
     *
     * @param  array<string, mixed>  $extra
     */
    private function message(ChatRoom $room, User $author, string $body, array $extra, CarbonInterface $at): void
    {
        ChatMessage::factory()
            ->from($author)
            ->create([
                'chat_room_id' => $room->id,
                'body' => $body,
                ...$extra,
                'created_at' => $at,
            ]);
    }

    /**
     * Write a seeded image and return the path to store on the message.
     *
     * `Filesystem::put()` returns a bool, not the path — the path is only
     * returned by `UploadedFile::store()`, which the payment action uses. Storing
     * `put()`'s return value directly writes the literal string "1" into the
     * column, so the path is built here and the write is asserted separately.
     */
    private function writeImage(Filesystem $disk, string $directory, string $name): string
    {
        $path = $directory.'/'.$name;

        if ($disk->put($path, $this->placeholderPng()) !== true) {
            throw new RuntimeException("Could not write the seeded image to [{$path}].");
        }

        return $path;
    }

    /**
     * A real 1x1 PNG, so the seeded attachments actually render.
     *
     * Deliberately not a working QRIS: nothing in the app decodes one, and a
     * seeded demo should never look payable. It only has to be a valid image
     * for the thread to look like a live one.
     */
    private function placeholderPng(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk'
            .'YPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
            true,
        );
    }
}
