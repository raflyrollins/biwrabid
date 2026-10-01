export type User = {
    id: number;
    uuid: string;
    name: string;
    email: string;
    avatar: string | null;
    phone: string | null;
    is_admin: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
};

export type Auth = {
    user: User | null;
};
