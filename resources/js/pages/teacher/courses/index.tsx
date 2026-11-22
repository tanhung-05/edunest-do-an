import { Head } from '@inertiajs/react';
import { CircleAlert, FilePen, Hourglass, Store } from 'lucide-react';
import { AuthorCourseList } from '@/components/author-course-list';
import { Page } from '@/components/page';
import type { CourseRow } from '@/types/course';

/** MH-GV-02: "Khóa tôi soạn". P10: dải đầu trang tóm tắt việc soạn cần làm. */
export default function TeacherCourses({ courses }: { courses: CourseRow[] }) {
    const by = (st: string) =>
        courses.filter((c) => c.open_version?.status === st).length;
    const returned = by('TRA_LAI');
    const overdue = courses.filter((c) => c.overdue).length;

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
            <AuthorCourseList courses={courses} />
        </Page>
    );
}
