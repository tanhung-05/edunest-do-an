import { Head } from '@inertiajs/react';
import { CourseTree } from '@/components/course-tree';
import { Block, Page } from '@/components/page';
import type { SectionNode } from '@/types/course';

/** Xem trước bản nháp như học viên (NH-61). Không ghi tiến độ, lượt, XP (CR-20). */
export default function CoursePreview({
    course,
    version,
    sections,
}: {
    course: { id: number; title: string };
    version: { id: number; version_no: number; status_label: string };
    sections: SectionNode[];
}) {
    return (
        <Page
            title={`Xem trước: ${course.title}`}
            description={`Bản ${version.version_no} · ${version.status_label}`}
        >
            <Head title={`Xem trước – ${course.title}`} />
            <Block tone="accent">
                <p className="font-semibold">
                    Chế độ xem trước: không ghi tiến độ, lượt làm bài hay điểm.
                </p>
            </Block>
            <CourseTree sections={sections} />
        </Page>
    );
}
