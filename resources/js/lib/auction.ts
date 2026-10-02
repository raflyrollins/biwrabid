import type { BadgeVariant } from '@/components/ui/badge';
import type { AuctionStatus } from '@/types/auction';

export function statusVariant(status: AuctionStatus): BadgeVariant {
    switch (status) {
        case 'active':
            return 'success';
        case 'paid':
            return 'brand';
        case 'cancelled':
            return 'danger';
        case 'draft':
            return 'gray';
        case 'ended':
            return 'neutral';
    }
}
