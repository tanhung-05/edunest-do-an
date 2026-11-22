import { Link } from '@inertiajs/react';
import { LogoMark } from '@/components/app-logo';
import { AppDock, AppRail } from '@/components/app-sidebar';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { ChatWidget } from '@/components/chat-widget';
import { TooltipProvider } from '@/components/ui/tooltip';
import { dashboard } from '@/routes';
import type { AppLayoutProps } from '@/types';

/** Khung vùng ứng dụng v5: thanh biểu tượng cố định, thanh trên mỏng có đường dẫn, nội dung sát lên. */
export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <TooltipProvider delayDuration={120}>
            <div className="min-h-svh bg-background">
                <AppRail />
                <div className="flex min-h-svh min-w-0 flex-col overflow-x-clip pb-20 md:pb-0 md:pl-[76px]">
                    {breadcrumbs.length > 0 ? (
                        <header className="flex h-12 shrink-0 items-center gap-3 border-b border-border bg-card/70 px-4 backdrop-blur md:px-8">
                            <Link
                                href={dashboard()}
                                aria-label="EduNest"
                                className="md:hidden"
                            >
                                <LogoMark className="size-7 rounded-lg" />
                            </Link>
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        </header>
                    ) : (
                        <header className="flex h-12 items-center px-4 md:hidden">
                            <Link href={dashboard()} aria-label="EduNest">
                                <LogoMark className="size-7 rounded-lg" />
                            </Link>
                        </header>
                    )}
                    <main
                        id="noi-dung"
                        className="flex min-w-0 flex-1 flex-col"
                    >
                        {children}
                    </main>
                </div>
                <AppDock />
                <ChatWidget aboveDock />
            </div>
        </TooltipProvider>
    );
}
