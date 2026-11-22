import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    BookOpenCheck,
    ChartNoAxesColumn,
    ShieldCheck,
} from 'lucide-react';
import { useRef } from 'react';
import { LogoMark } from '@/components/app-logo';
import { CountUp } from '@/components/motion/count-up';
import { gsap, MOTION_OK, SplitText, useGSAP } from '@/components/motion/gsap';
import { register } from '@/routes';
import publicRoutes from '@/routes/public';

type Teacher = {
    id: number;
    name: string;
    photo: string | null;
    bio: string | null;
    achievements: string | null;
};

type Props = {
    teachers: Teacher[];
    stats: {
        courses: number;
        students: number;
        teachers: number;
        certificates: number;
    };
};

const PRINCIPLES = [
    {
        icon: ShieldCheck,
        title: 'Giáo vụ duyệt từng khóa',
        text: 'Giáo viên soạn, giáo vụ đọc lại đề cương, video và câu hỏi trước khi khóa được mở bán. Khóa có lỗi được trả về kèm góp ý cụ thể.',
    },
    {
        icon: BookOpenCheck,
        title: 'Học theo nhịp của mình',
        text: 'Hệ thống nhớ chỗ bạn dừng trong video, mở bài tiếp theo khi bạn sẵn sàng và nhắc ôn lại thẻ ghi nhớ đúng lúc sắp quên.',
    },
    {
        icon: ChartNoAxesColumn,
        title: 'Tiến bộ đo được',
        text: 'Quiz chấm ngay khi nộp, bảng học tập cho thấy chuỗi ngày học và điểm kinh nghiệm. Phụ huynh xem được tiến độ của con.',
    },
    {
        icon: BadgeCheck,
        title: 'Chứng nhận xác minh công khai',
        text: 'Hoàn thành khóa, bạn nhận chứng nhận có mã và mã QR. Ai cũng có thể kiểm tra chứng nhận là thật trên EduNest.',
    },
];

const JOURNEY = [
    {
        who: 'Giáo viên',
        what: 'Soạn khóa học: video, tài liệu, quiz, đề thi và thẻ ghi nhớ.',
    },
    {
        who: 'Giáo vụ',
        what: 'Kiểm duyệt nội dung, mở bán, xếp lịch buổi học trực tiếp.',
    },
    {
        who: 'Học viên',
        what: 'Học thử, mua khóa, học, làm bài và nhận chứng nhận.',
    },
    {
        who: 'Phụ huynh',
        what: 'Liên kết tài khoản với con để theo dõi tiến độ và kết quả.',
    },
];

function initials(name: string) {
    return name.trim().split(/\s+/).at(-1)?.[0] ?? '';
}

export default function About({ teachers, stats }: Props) {
    const scope = useRef<HTMLDivElement>(null);
    const signedIn = Boolean(
        (usePage().props as { auth?: { user?: unknown } }).auth?.user,
    );

    useGSAP(
        () => {
            const mm = gsap.matchMedia();
            mm.add(MOTION_OK, () => {
                const title = scope.current?.querySelector('[data-hero-title]');
                const split = title
                    ? SplitText.create(title, {
                          type: 'words',
                          mask: 'words',
                          wordsClass: 'split-word',
                          aria: 'auto',
                      })
                    : null;
                gsap.timeline({ defaults: { ease: 'expo.out' } })
                    .from('[data-hero-photo]', {
                        scale: 1.12,
                        duration: 2.2,
                        ease: 'power3.out',
                    })
                    .from(
                        split?.words ?? [],
                        { yPercent: 115, duration: 1, stagger: 0.06 },
                        0.2,
                    )
                    .from(
                        '[data-hero-fade]',
                        {
                            y: 20,
                            opacity: 0,
                            duration: 0.8,
                            stagger: 0.08,
                            clearProps: 'opacity,transform',
                        },
                        0.5,
                    );

                gsap.from('[data-mark]', {
                    scale: 0.6,
                    rotate: -8,
                    opacity: 0,
                    duration: 1.1,
                    ease: 'back.out(1.6)',
                    clearProps: 'all',
                    scrollTrigger: { trigger: '[data-mark]', start: 'top 85%' },
                });
                gsap.from('[data-teacher]', {
                    y: 48,
                    opacity: 0,
                    duration: 0.9,
                    stagger: 0.08,
                    ease: 'power3.out',
                    clearProps: 'opacity,transform',
                    scrollTrigger: {
                        trigger: '[data-team]',
                        start: 'top 80%',
                    },
                });

                return () => split?.revert();
            });

            return () => mm.revert();
        },
        { scope },
    );

    const figures = [
        { value: stats.courses, label: 'khóa đang mở' },
        { value: stats.students, label: 'học viên đã ghi danh' },
        { value: stats.teachers, label: 'giảng viên' },
        { value: stats.certificates, label: 'chứng nhận đã cấp' },
    ];

    return (
        <div ref={scope}>
            <Head title="Giới thiệu" />

            <section className="relative isolate overflow-hidden bg-slate-950 pt-36 pb-16 text-white md:pt-44 md:pb-20">
                <img
                    data-hero-photo
                    src="/images/stock/thu-vien.jpg"
                    alt=""
                    fetchPriority="high"
                    className="absolute inset-0 -z-20 h-full w-full object-cover opacity-60"
                />
                <div className="absolute inset-0 -z-10 bg-gradient-to-b from-slate-950/70 via-slate-950/60 to-slate-950" />
                <div className="mx-auto w-full max-w-7xl px-4 md:px-8">
                    <p
                        data-hero-fade
                        className="text-sm font-semibold tracking-[0.2em] text-amber uppercase"
                    >
                        Về EduNest
                    </p>
                    <h1
                        data-hero-title
                        className="mt-5 max-w-4xl font-display text-[clamp(2.4rem,5.6vw,4.8rem)] leading-[1.05] font-bold tracking-tight text-balance"
                    >
                        Một chiếc tổ để việc học lớn lên mỗi ngày
                    </h1>
                    <p
                        data-hero-fade
                        className="mt-6 max-w-2xl text-lg text-white/75 md:text-xl"
                    >
                        EduNest là nền tảng học trực tuyến nơi giáo viên giỏi
                        soạn khóa học, giáo vụ kiểm duyệt và học viên học theo
                        nhịp của mình, có phụ huynh cùng theo dõi.
                    </p>
                    <dl className="mt-14 grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-white/10 bg-white/10 md:grid-cols-4">
                        {figures.map((f) => (
                            <div
                                key={f.label}
                                data-hero-fade
                                className="bg-slate-950/70 p-5 backdrop-blur md:p-6"
                            >
                                <dt className="text-sm text-white/60">
                                    {f.label}
                                </dt>
                                <dd className="mt-1 font-display text-3xl font-bold md:text-4xl">
                                    <CountUp value={f.value} />
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>
            </section>

            <section className="mx-auto grid w-full max-w-7xl items-center gap-12 px-4 pt-24 md:px-8 md:pt-32 lg:grid-cols-[1.15fr_1fr] lg:gap-20">
                <div>
                    <h2 className="font-display text-[clamp(2rem,3.6vw,3rem)] leading-tight font-bold tracking-tight">
                        Vì sao có EduNest
                    </h2>
                    <div className="mt-6 grid gap-4 text-lg leading-relaxed text-muted-foreground">
                        <p>
                            Nhiều học sinh muốn học thêm với giáo viên giỏi
                            nhưng không có thời gian tới lớp. Nhiều giáo viên có
                            bài giảng tốt nhưng thiếu một nơi để đưa bài giảng
                            tới đúng người học.
                        </p>
                        <p>
                            EduNest gom cả hai phía vào một chỗ: giáo viên soạn
                            khóa, nhà trường kiểm duyệt chất lượng, học viên học
                            và làm bài ngay trên máy tính hoặc điện thoại. Mọi
                            kết quả đều được ghi lại để người học, phụ huynh và
                            giáo viên cùng nhìn thấy.
                        </p>
                    </div>
                </div>
                <figure className="relative overflow-hidden rounded-3xl border border-border bg-card p-8 shadow-soft md:p-10">
                    <div className="flex items-center gap-5">
                        <span data-mark className="inline-flex">
                            <LogoMark className="size-24 rounded-[1.6rem] md:size-28" />
                        </span>
                        <div>
                            <p className="font-display text-2xl font-bold tracking-tight">
                                Edu<span className="text-amber-600">Nest</span>
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Ý nghĩa biểu tượng
                            </p>
                        </div>
                    </div>
                    <figcaption className="mt-8 grid gap-4 text-base leading-relaxed">
                        <p>
                            <strong className="font-semibold">
                                Cuốn sách mở
                            </strong>{' '}
                            cong lại thành{' '}
                            <strong className="font-semibold">chiếc tổ</strong>,
                            là nơi kiến thức được giữ gìn và truyền lại.
                        </p>
                        <p>
                            <strong className="font-semibold">
                                Chấm tròn màu hổ phách
                            </strong>{' '}
                            là người học, được nâng đỡ ở giữa và lớn lên từ đó.
                        </p>
                        <p className="text-muted-foreground">
                            Nền xanh than thể hiện sự tin cậy; màu hổ phách dành
                            cho những gì quan trọng nhất.
                        </p>
                    </figcaption>
                </figure>
            </section>

            <section className="mx-auto w-full max-w-7xl px-4 pt-24 md:px-8 md:pt-32">
                <h2 className="max-w-2xl font-display text-[clamp(2rem,3.6vw,3rem)] leading-tight font-bold tracking-tight">
                    Điều chúng tôi giữ cho mỗi khóa học
                </h2>
                <div className="mt-10 grid gap-10 lg:grid-cols-[1fr_1.1fr] lg:gap-16">
                    <div className="relative hidden photo-zoom overflow-hidden rounded-3xl bg-slate-200 lg:block">
                        <img
                            src="/images/stock/nhom-macbook.jpg"
                            alt="Nhóm học viên cùng học trên máy tính"
                            loading="lazy"
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                    </div>
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                        {PRINCIPLES.map(({ icon: Icon, title, text }) => (
                            <li
                                key={title}
                                className="grid grid-cols-[3rem_1fr] gap-4 rounded-2xl border border-border bg-card p-5"
                            >
                                <span className="grid size-12 place-items-center rounded-xl bg-slate-950 text-amber">
                                    <Icon className="size-5" aria-hidden />
                                </span>
                                <span>
                                    <strong className="block text-lg font-semibold">
                                        {title}
                                    </strong>
                                    <span className="mt-1 block text-muted-foreground">
                                        {text}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            </section>

            {teachers.length > 0 && (
                <section
                    data-team
                    aria-labelledby="doi-ngu"
                    className="mx-auto w-full max-w-7xl px-4 pt-24 md:px-8 md:pt-32"
                >
                    <div className="mb-10 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h2
                                id="doi-ngu"
                                className="font-display text-[clamp(2rem,3.6vw,3rem)] leading-tight font-bold tracking-tight"
                            >
                                Đội ngũ giảng viên
                            </h2>
                            <p className="mt-3 max-w-xl text-muted-foreground">
                                Mỗi giảng viên có hồ sơ công khai, ghi rõ kinh
                                nghiệm và các khóa đang dạy.
                            </p>
                        </div>
                        <Link
                            href={publicRoutes.courses.index()}
                            className="group inline-flex items-center gap-2 text-sm font-semibold"
                        >
                            Xem khóa học của giảng viên
                            <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
                        </Link>
                    </div>
                    <ul className="grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-x-6 sm:gap-y-10 lg:grid-cols-3">
                        {teachers.map((t) => (
                            <li key={t.id} data-teacher>
                                <Link
                                    href={publicRoutes.teachers.show(t.id)}
                                    className="group block"
                                >
                                    <span className="relative block aspect-[4/5] photo-zoom overflow-hidden rounded-2xl bg-slate-200 sm:rounded-3xl">
                                        {t.photo ? (
                                            <img
                                                src={t.photo}
                                                alt={`Ảnh giảng viên ${t.name}`}
                                                loading="lazy"
                                                className="absolute inset-0 h-full w-full object-cover"
                                            />
                                        ) : (
                                            <span
                                                aria-hidden
                                                className="absolute inset-0 grid place-items-center bg-slate-100 font-display text-6xl font-bold text-slate-400"
                                            >
                                                {initials(t.name)}
                                            </span>
                                        )}
                                        <span className="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-slate-950/80 to-transparent" />
                                        {t.achievements && (
                                            <span className="absolute inset-x-4 bottom-4 line-clamp-2 hidden text-sm text-white/90 sm:block">
                                                {t.achievements}
                                            </span>
                                        )}
                                    </span>
                                    <span className="mt-4 flex items-start justify-between gap-3">
                                        <span className="min-w-0">
                                            <span className="block font-display text-base font-bold tracking-tight sm:text-xl">
                                                {t.name}
                                            </span>
                                            {t.bio && (
                                                <span className="mt-1 block text-sm text-muted-foreground sm:text-base">
                                                    {t.bio}
                                                </span>
                                            )}
                                        </span>
                                        <span className="mt-1 hidden size-9 shrink-0 place-items-center rounded-full border border-border transition-colors group-hover:border-slate-950 group-hover:bg-slate-950 group-hover:text-white sm:grid">
                                            <ArrowRight
                                                className="size-4"
                                                aria-hidden
                                            />
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section className="mx-auto w-full max-w-7xl px-4 pt-24 md:px-8 md:pt-32">
                <h2 className="max-w-2xl font-display text-[clamp(2rem,3.6vw,3rem)] leading-tight font-bold tracking-tight">
                    Một khóa học đi qua những ai
                </h2>
                <ol className="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {JOURNEY.map((step, index) => (
                        <li
                            key={step.who}
                            className="relative rounded-2xl border border-border bg-card p-6"
                        >
                            <span className="font-display text-5xl font-bold text-slate-200">
                                {String(index + 1).padStart(2, '0')}
                            </span>
                            <strong className="mt-4 block text-lg font-semibold">
                                {step.who}
                            </strong>
                            <span className="mt-1 block text-muted-foreground">
                                {step.what}
                            </span>
                        </li>
                    ))}
                </ol>
            </section>

            <section className="mx-auto w-full max-w-7xl px-4 pt-24 pb-24 md:px-8 md:pt-32 md:pb-32">
                <div className="relative isolate overflow-hidden rounded-3xl bg-slate-950 px-6 py-14 text-white md:px-14 md:py-16">
                    <img
                        src="/images/stock/hoc-dem.jpg"
                        alt=""
                        loading="lazy"
                        className="absolute inset-0 -z-20 h-full w-full object-cover opacity-35"
                    />
                    <div className="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950 via-slate-950/85 to-slate-950/30" />
                    <h2 className="max-w-xl font-display text-[clamp(1.8rem,3.2vw,2.6rem)] leading-tight font-bold tracking-tight">
                        Bắt đầu bằng một bài học thử miễn phí
                    </h2>
                    <p className="mt-4 max-w-lg text-white/75">
                        Mỗi khóa đều có bài học thử. Xem trước cách giảng viên
                        dạy rồi mới quyết định đăng ký.
                    </p>
                    <div className="mt-8 flex flex-wrap gap-3 text-sm font-semibold">
                        <Link
                            href={publicRoutes.courses.index()}
                            className="inline-flex items-center gap-2 rounded-full bg-amber px-6 py-3 text-slate-950 transition-transform hover:-translate-y-px active:scale-[0.98]"
                        >
                            Xem khóa học
                            <ArrowRight className="size-4" aria-hidden />
                        </Link>
                        {!signedIn && (
                            <Link
                                href={register()}
                                className="inline-flex items-center rounded-full border border-white/25 px-6 py-3 transition-colors hover:bg-white hover:text-slate-950"
                            >
                                Tạo tài khoản
                            </Link>
                        )}
                    </div>
                </div>
            </section>
        </div>
    );
}
