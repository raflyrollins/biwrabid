import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            translations: Record<string, unknown>;
            locale: string;
            currency: string;
            flash: {
                status: string | null;
                error: string | null;
            };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
