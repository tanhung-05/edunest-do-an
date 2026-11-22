import { Head } from '@inertiajs/react';
import { Page, Stat } from '@/components/page';

type Props = {
    stats: { users: number; locked: number; categories: number };
};

export default function AdminDashboard({ stats }: Props) {
    return (
        <Page
            title="Tổng quan quản trị"
            description="Tình trạng hệ thống, người dùng và cấu hình chung của EduNest."
        >
            <Head title="Tổng quan quản trị" />
            <div className="grid gap-4 md:grid-cols-3">
                <Stat label="Tài khoản" value={stats.users} tone="surface" />
                <Stat
                    label="Tài khoản bị khóa"
                    value={stats.locked}
                    tone="accent"
                />
                <Stat label="Danh mục" value={stats.categories} />
            </div>
        </Page>
    );
}
