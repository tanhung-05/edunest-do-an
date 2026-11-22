import { Head } from '@inertiajs/react';
import {
    CircleAlert,
    FilePen,
    Hourglass,
    LayoutGrid,
    List,
    Search,
    Store,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { AuthorCourseList } from '@/components/author-course-list';
import { Page, selectClass } from '@/components/page';
import { Input } from '@/components/ui/input';
import type { CourseRow } from '@/types/course';

type StatusFilter =
    | ''
    | 'NHAP'
    | 'TRA_LAI'
    | 'CHO_DUYET'
    | 'DANG_BAN'
    | 'NGUNG_BAN';
type Sort = 'han' | 'ten' | 'moi';
type Layout = 'grid' | 'list';

const statusOptions: { value: StatusFilter; label: string }[] = [
    { value: '', label: 'Mọi trạng thái' },
    { value: 'NHAP', label: 'Bản nháp' },
    { value: 'TRA_LAI', label: 'Bị trả lại' },
    { value: 'CHO_DUYET', label: 'Chờ duyệt' },
    { value: 'DANG_BAN', label: 'Đang bán' },
    { value: 'NGUNG_BAN', label: 'Ngừng bán' },
];

const LAYOUT_KEY = 'teacher-courses-layout';

function readLayout(): Layout {
    try {
        return localStorage.getItem(LAYOUT_KEY) === 'list' ? 'list' : 'grid';
    } catch {
        return 'grid';
    }
}

/**
 * MH-GV-02: "Khóa tôi soạn". P10: dải đầu trang tóm tắt việc soạn cần làm.
 * UAT 22/11: tìm theo tên, lọc trạng thái, sắp xếp và đổi dạng thẻ hoặc bảng.
 */
export default function TeacherCourses({ courses }: { courses: CourseRow[] }) {
    const by = (st: string) =>
        courses.filter((c) => c.open_version?.status === st).length;
    const returned = by('TRA_LAI');
    const overdue = courses.filter((c) => c.overdue).length;

    const [q, setQ] = useState('');
    const [status, setStatus] = useState<StatusFilter>('');
    const [sort, setSort] = useState<Sort>('han');
    const [layout, setLayout] = useState<Layout>(readLayout);

    const changeLayout = (next: Layout) => {
        setLayout(next);

        try {
            localStorage.setItem(LAYOUT_KEY, next);
        } catch {
            // Trình duyệt chặn lưu trữ: chỉ đổi trong lần xem này.
        }
    };

    const shown = useMemo(() => {
        const key = q.trim().toLocaleLowerCase('vi');
        const list = courses.filter((c) => {
            if (key && !c.title.toLocaleLowerCase('vi').includes(key)) {
                return false;
            }

            if (status === 'DANG_BAN' || status === 'NGUNG_BAN') {
                return c.sale_status === status;
            }

            return status === '' || c.open_version?.status === status;
        });

        return [...list].sort((a, b) => {
            if (sort === 'ten') {
                return a.title.localeCompare(b.title, 'vi');
            }

            if (sort === 'moi') {
                return b.id - a.id;
            }

            return (a.due_date ?? '9999').localeCompare(b.due_date ?? '9999');
        });
    }, [courses, q, status, sort]);

    const toggleClass = (active: boolean) =>
        `inline-flex h-full w-10 items-center justify-center ${active ? 'bg-accent text-foreground' : 'text-muted-foreground'}`;

    return (
        <Page
            title="Khóa tôi soạn"
            description="Các khóa được giao soạn: trạng thái bản nháp, bản chờ duyệt và bản đang bán. Mở trình soạn để tiếp tục."
            stats={[
                {
                    label: 'Đang bán',
                    value: courses.filter((c) => c.sale_status === 'DANG_BAN')
                        .length,
                    icon: Store,
                },
                { label: 'Bản nháp', value: by('NHAP'), icon: FilePen },
                { label: 'Chờ duyệt', value: by('CHO_DUYET'), icon: Hourglass },
                {
                    label: 'Bị trả lại hoặc quá hạn',
                    value: returned + overdue,
                    icon: CircleAlert,
                    attention: returned + overdue > 0,
                },
            ]}
        >
            <Head title="Khóa tôi soạn" />
            {courses.length > 0 && (
                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <div className="relative min-w-48 flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Tìm theo tên khóa"
                            aria-label="Tìm theo tên khóa"
                            className="pl-9"
                        />
                    </div>
                    <select
                        aria-label="Lọc theo trạng thái"
                        className={`${selectClass} sm:w-auto`}
                        value={status}
                        onChange={(e) =>
                            setStatus(e.target.value as StatusFilter)
                        }
                    >
                        {statusOptions.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Sắp xếp"
                        className={`${selectClass} sm:w-auto`}
                        value={sort}
                        onChange={(e) => setSort(e.target.value as Sort)}
                    >
                        <option value="han">Hạn soạn gần nhất</option>
                        <option value="moi">Mới giao gần đây</option>
                        <option value="ten">Tên A đến Z</option>
                    </select>
                    <div
                        role="group"
                        aria-label="Dạng xem"
                        className="flex h-10 shrink-0 overflow-hidden rounded-lg border border-input"
                    >
                        <button
                            type="button"
                            aria-label="Xem dạng thẻ"
                            aria-pressed={layout === 'grid'}
                            onClick={() => changeLayout('grid')}
                            className={toggleClass(layout === 'grid')}
                        >
                            <LayoutGrid className="size-4" />
                        </button>
                        <button
                            type="button"
                            aria-label="Xem dạng bảng"
                            aria-pressed={layout === 'list'}
                            onClick={() => changeLayout('list')}
                            className={toggleClass(layout === 'list')}
                        >
                            <List className="size-4" />
                        </button>
                    </div>
                </div>
            )}
            {courses.length > 0 && shown.length === 0 ? (
                <p className="rounded-2xl border border-dashed border-border p-5 text-muted-foreground">
                    Không có khóa nào khớp điều kiện tìm.
                </p>
            ) : (
                <AuthorCourseList courses={shown} layout={layout} />
            )}
        </Page>
    );
}
