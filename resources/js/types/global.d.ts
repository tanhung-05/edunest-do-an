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
            sidebarOpen: boolean;
            flash: { status?: string | null };
            notifications: { unread: number; reverb: boolean } | null;
            cart_count: number;
            [key: string]: unknown;
        };
    }
}
