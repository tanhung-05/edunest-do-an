import { Link, usePage } from '@inertiajs/react';
import {
    Award,
    BookOpen,
    Heart,
    KeyRound,
    BadgePercent,
    MessageSquareText,
    MonitorSmartphone,
    ShoppingCart,
    Ticket,
    ChartColumn,
    GraduationCap,
    IdCard,
    LayoutGrid,
    Library,
    PenLine,
    Receipt,
    ScrollText,
    Settings,
    ShieldCheck,
    Tags,
    User,
    UserCog,
    Users,
    Wallet,
    ClipboardList,
    MessagesSquare,
    Trophy,
    Target,
    Landmark,
    Layers,
    Flame,
    Medal,
    UsersRound,
    Video,
    HandCoins,
    Headset,
} from 'lucide-react';
import { LogoMark } from '@/components/app-logo';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { NotificationBell } from '@/components/notification-bell';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import academic from '@/routes/academic';
import account from '@/routes/account';
import admin from '@/routes/admin';
import exams from '@/routes/exams';
import guardian from '@/routes/guardian';
import student from '@/routes/student';
import teacher from '@/routes/teacher';
import type { NavGroup, NavItem, RoleCode } from '@/types';

const groupsByRole: Record<RoleCode, NavGroup | null> = {
    HOC_VIEN: {
        title: 'Học viên',
        items: [
            { title: 'Tổng quan', href: student.dashboard(), icon: LayoutGrid },
            {
                title: 'Khóa của tôi',
                href: student.courses.index(),
                icon: BookOpen,
            },
            {
                title: 'Chứng nhận',
                href: student.certificates.index(),
                icon: Award,
            },
            { title: 'Kỳ thi thử', href: exams.index(), icon: Trophy },
            {
                title: 'Luyện tập',
                href: student.practice.index(),
                icon: Target,
            },
            {
                title: 'Thẻ ghi nhớ',
                href: student.flashcards.index(),
                icon: Layers,
            },
            {
                title: 'Thành tích',
                href: student.achievements.index(),
                icon: Flame,
            },
            {
                title: 'Buổi trực tiếp',
                href: student.live.index(),
                icon: Video,
            },
            {
                title: 'Cộng đồng',
                href: student.feed.index(),
                icon: MessagesSquare,
            },
            {
                title: 'Phụ huynh',
                href: student.guardians.index(),
                icon: UsersRound,
            },
            { title: 'Đơn hàng', href: student.orders.index(), icon: Receipt },
            {
                title: 'Giỏ hàng',
                href: student.cart.index(),
                icon: ShoppingCart,
            },
            {
                title: 'Khóa yêu thích',
                href: student.wishlist.index(),
                icon: Heart,
            },
            {
                title: 'Nhập mã kích hoạt',
                href: student.activation.create(),
                icon: KeyRound,
            },
        ],
    },
    GIAO_VIEN: {
        title: 'Giáo viên',
        items: [
            { title: 'Tổng quan', href: teacher.dashboard(), icon: LayoutGrid },
            {
                title: 'Khóa tôi soạn',
                href: teacher.courses.index(),
                icon: PenLine,
            },
            {
                title: 'Hồ sơ giáo viên',
                href: teacher.profile.edit(),
                icon: IdCard,
            },
            {
                title: 'Đánh giá khóa học',
                href: teacher.reviews.index(),
                icon: MessageSquareText,
            },
            {
                title: 'Hỏi đáp',
                href: teacher.discussions.index(),
                icon: MessagesSquare,
            },
            {
                title: 'Đề thi thử',
                href: teacher.examPapers.index(),
                icon: ClipboardList,
            },
            { title: 'Kỳ thi', href: teacher.exams.index(), icon: Trophy },
            {
                title: 'Hoạt động luyện tập',
                href: teacher.activities.index(),
                icon: Target,
            },
            {
                title: 'Bộ thẻ ghi nhớ',
                href: teacher.flashcardSets.index(),
                icon: Layers,
            },
            {
                title: 'Buổi trực tiếp',
                href: teacher.liveSessions.index(),
                icon: Video,
            },
        ],
    },
    GIAO_VU: {
        title: 'Giáo vụ',
        items: [
            {
                title: 'Tổng quan',
                href: academic.dashboard(),
                icon: LayoutGrid,
            },
            {
                title: 'Khóa học',
                href: academic.courses.index(),
                icon: Library,
            },
            {
                title: 'Giáo viên',
                href: academic.teachers.index(),
                icon: Users,
            },
            { title: 'Đơn hàng', href: academic.orders.index(), icon: Receipt },
            {
                title: 'Chat hỗ trợ',
                href: academic.support.index(),
                icon: Headset,
            },
            {
                title: 'Chuyển khoản chờ tra soát',
                href: academic.bankReviews.index(),
                icon: Landmark,
            },
            {
                title: 'Khuyến mãi khóa',
                href: academic.promotions.index(),
                icon: BadgePercent,
            },
            {
                title: 'Mã giảm giá',
                href: academic.coupons.index(),
                icon: Ticket,
            },
            {
                title: 'Mã kích hoạt',
                href: academic.activationBatches.index(),
                icon: KeyRound,
            },
            { title: 'Combo', href: academic.bundles.index(), icon: Layers },
            {
                title: 'Cộng tác viên',
                href: academic.affiliates.index(),
                icon: UsersRound,
            },
            {
                title: 'Kiểm duyệt đánh giá',
                href: academic.moderation.index(),
                icon: MessageSquareText,
            },
            {
                title: 'Kiểm duyệt hỏi đáp',
                href: academic.discussions.index(),
                icon: MessagesSquare,
            },
            {
                title: 'Kiểm duyệt cộng đồng',
                href: academic.community.index(),
                icon: ShieldCheck,
            },
            { title: 'Kỳ thi', href: academic.exams.index(), icon: Trophy },
            {
                title: 'Học viên và ghi danh',
                href: academic.enrollments.index(),
                icon: GraduationCap,
            },
            {
                title: 'Chứng nhận',
                href: academic.certificates.index(),
                icon: Award,
            },
            {
                title: 'Báo cáo',
                href: academic.reports.index(),
                icon: ChartColumn,
            },
        ],
    },
    QUAN_TRI: {
        title: 'Quản trị',
        items: [
            { title: 'Tổng quan', href: admin.dashboard(), icon: LayoutGrid },
            { title: 'Tài khoản', href: admin.users.index(), icon: UserCog },
            {
                title: 'Danh mục nền',
                href: admin.taxonomies.index(),
                icon: Tags,
            },
            { title: 'Cấu hình', href: admin.settings.edit(), icon: Settings },
            {
                title: 'Nhật ký thao tác',
                href: admin.auditLogs.index(),
                icon: ScrollText,
            },
            { title: 'Doanh thu', href: admin.revenue.index(), icon: Wallet },
            { title: 'Huy hiệu', href: admin.badges.index(), icon: Medal },
        ],
    },
    PHU_HUYNH: {
        title: 'Phụ huynh',
        items: [
            {
                title: 'Con của tôi',
                href: guardian.dashboard(),
                icon: UsersRound,
            },
            { title: 'Kỳ thi thử', href: exams.index(), icon: Trophy },
        ],
    },
};

const accountGroup: NavGroup = {
    title: 'Tài khoản của tôi',
    items: [
        { title: 'Hồ sơ', href: account.profile.edit(), icon: User },
        { title: 'Bảo mật', href: account.security.edit(), icon: ShieldCheck },
        {
            title: 'Thiết bị của tôi',
            href: account.devices.index(),
            icon: MonitorSmartphone,
        },
    ],
};

function useNavGroups(): NavGroup[] {
    const {
        props: { auth },
        url,
    } = usePage();
    const roles = auth.user?.effective_roles ?? [];
    const teacherCourseId = Number(
        url.match(/^\/teacher\/courses\/(\d+)(?:[/?]|$)/)?.[1],
    );

    return roles
        .map((role) => {
            const group = groupsByRole[role];

            if (role === 'HOC_VIEN' && group && auth.user?.is_affiliate) {
                return {
                    ...group,
                    items: [
                        ...group.items,
                        {
                            title: 'Tiếp thị liên kết',
                            href: student.affiliate.index(),
                            icon: HandCoins,
                        },
                    ],
                };
            }

            if (role === 'GIAO_VIEN' && group && teacherCourseId > 0) {
                return {
                    ...group,
                    items: [
                        ...group.items,
                        {
                            title: 'Báo cáo quiz',
                            href: teacher.courses.quizReport(teacherCourseId),
                            icon: ChartColumn,
                        },
                    ],
                };
            }

            return group;
        })
        .filter((g): g is NavGroup => g !== null);
}

function RailLink({ item, active }: { item: NavItem; active: boolean }) {
    const Icon = item.icon;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Link
                    href={item.href}
                    prefetch
                    aria-label={item.title}
                    aria-current={active ? 'page' : undefined}
                    className={cn(
                        'grid size-11 place-items-center rounded-xl transition-[background-color,color,transform] duration-200 active:scale-95',
                        active
                            ? 'bg-primary text-primary-foreground shadow-soft'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                    )}
                >
                    {Icon && <Icon className="size-[1.2rem]" />}
                </Link>
            </TooltipTrigger>
            <TooltipContent side="right" sideOffset={10}>
                {item.title}
            </TooltipContent>
        </Tooltip>
    );
}

function UserButton({ side }: { side: 'right' | 'top' }) {
    const { auth } = usePage().props;

    if (!auth.user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                aria-label="Tài khoản"
                data-test="sidebar-menu-button"
                className="grid size-11 place-items-center rounded-full outline-none hover:ring-2 hover:ring-border focus-visible:ring-2 focus-visible:ring-ring"
            >
                <span className="pointer-events-none flex [&>div]:hidden">
                    <UserInfo user={auth.user} />
                </span>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                side={side}
                align="end"
                sideOffset={10}
                className="min-w-60 rounded-xl"
            >
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * Thanh điều hướng chỉ có biểu tượng (he-thiet-ke-v5): dọc bên trái trên màn rộng,
 * ngang dưới đáy trên điện thoại. Tên mục ở chú giải khi rê chuột và ở aria-label.
 */
export function AppRail() {
    const groups = useNavGroups();
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <nav
            aria-label="Điều hướng chính"
            className="fixed inset-y-0 left-0 z-30 hidden w-[76px] flex-col items-center border-r border-border bg-sidebar py-4 md:flex"
        >
            <Link
                href={dashboard()}
                prefetch
                aria-label="EduNest, về trang tổng quan"
                className="mb-5 rounded-xl transition-transform hover:scale-105"
            >
                <LogoMark className="size-10 rounded-xl" />
            </Link>
            <div className="flex flex-1 flex-col items-center gap-1.5 overflow-y-auto">
                {groups.map((group, index) => (
                    <div
                        key={group.title}
                        role="group"
                        aria-label={group.title}
                        className={cn(
                            'flex flex-col items-center gap-1.5',
                            index > 0 && 'mt-2 border-t border-border pt-3.5',
                        )}
                    >
                        {group.items.map((item) => (
                            <RailLink
                                key={item.title}
                                item={item}
                                active={isCurrentUrl(item.href)}
                            />
                        ))}
                    </div>
                ))}
            </div>
            <div className="flex flex-col items-center gap-1.5 border-t border-border pt-3.5">
                <NotificationBell />
                {accountGroup.items.map((item) => (
                    <RailLink
                        key={item.title}
                        item={item}
                        active={isCurrentUrl(item.href)}
                    />
                ))}
                <div className="mt-1.5">
                    <UserButton side="right" />
                </div>
            </div>
        </nav>
    );
}

/** Thanh biểu tượng ngang ở đáy màn hình điện thoại. */
export function AppDock() {
    const groups = useNavGroups();
    const { isCurrentUrl } = useCurrentUrl();
    const items = groups.flatMap((group) => group.items);

    return (
        <nav
            aria-label="Điều hướng chính"
            className="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-sidebar/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
        >
            <div className="flex items-center gap-1 overflow-x-auto px-3 py-2">
                {items.map((item) => {
                    const Icon = item.icon;
                    const active = isCurrentUrl(item.href);

                    return (
                        <Link
                            key={item.title}
                            href={item.href}
                            prefetch
                            aria-label={item.title}
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'grid size-11 shrink-0 place-items-center rounded-xl',
                                active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {Icon && <Icon className="size-5" />}
                        </Link>
                    );
                })}
                <div className="ml-auto flex items-center gap-1 pl-2">
                    <NotificationBell />
                    <UserButton side="top" />
                </div>
            </div>
        </nav>
    );
}
