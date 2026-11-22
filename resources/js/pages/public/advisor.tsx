import { Head } from '@inertiajs/react';
import { Bot, Headset, Phone } from 'lucide-react';
import { useEffect } from 'react';
import { openChat } from '@/components/chat-widget';

type Props = {
    enabled: boolean;
    hotline: string;
};

/**
 * MH-KH tư vấn khóa học (QT-27c): từ P10 trợ lý nằm trong hộp chat nổi (CR-257). Trang này giữ đường dẫn cũ, mở sẵn
 * hộp chat và giới thiệu hai kênh tư vấn.
 */
export default function Advisor({ enabled, hotline }: Props) {
    useEffect(() => {
        const id = window.setTimeout(() => openChat('ai'), 300);

        return () => window.clearTimeout(id);
    }, []);

    return (
        <div className="mx-auto grid w-full max-w-5xl gap-8 px-4 py-12 md:px-8">
            <Head title="Tư vấn khóa học" />
            <header className="grid max-w-2xl gap-3">
                <p className="text-sm font-semibold tracking-wide text-amber-600 uppercase">
                    Tư vấn miễn phí
                </p>
                <h1 className="text-4xl font-bold tracking-tight md:text-5xl">
                    Chọn đúng khóa học trong vài phút
                </h1>
                <p className="text-lg text-muted-foreground">
                    Hỏi trợ lý AI bất cứ lúc nào, không giới hạn số câu. Cần tư
                    vấn sâu hơn về lộ trình, học phí, phụ huynh nhắn nhân viên
                    hoặc gọi hotline.
                </p>
            </header>
            <div className="grid gap-4 md:grid-cols-3">
                <button
                    type="button"
                    onClick={() => openChat('ai')}
                    disabled={!enabled}
                    className="grid gap-3 rounded-3xl border bg-card p-6 text-left shadow-sm transition-shadow hover:shadow-lg disabled:opacity-60"
                >
                    <span className="grid size-12 place-items-center rounded-2xl bg-slate-950 text-amber-300">
                        <Bot className="size-6" aria-hidden />
                    </span>
                    <span className="text-lg font-semibold">Hỏi trợ lý AI</span>
                    <span className="text-sm text-muted-foreground">
                        {enabled
                            ? 'Nêu môn, lớp, mục tiêu; trợ lý gợi ý ngay khóa đang mở bán kèm giá.'
                            : 'Trợ lý đang tạm nghỉ, bạn dùng hai kênh bên cạnh nhé.'}
                    </span>
                </button>
                <button
                    type="button"
                    onClick={() => openChat('staff')}
                    className="grid gap-3 rounded-3xl border bg-card p-6 text-left shadow-sm transition-shadow hover:shadow-lg"
                >
                    <span className="grid size-12 place-items-center rounded-2xl bg-amber-400 text-slate-950">
                        <Headset className="size-6" aria-hidden />
                    </span>
                    <span className="text-lg font-semibold">
                        Nhắn nhân viên
                    </span>
                    <span className="text-sm text-muted-foreground">
                        Không cần đăng nhập. Để lại họ tên, số điện thoại; nhân
                        viên trả lời trong khung chat và gọi lại.
                    </span>
                </button>
                <a
                    href={`tel:${hotline.replace(/[^\d+]/g, '')}`}
                    className="grid gap-3 rounded-3xl border bg-slate-950 p-6 text-white shadow-sm transition-shadow hover:shadow-lg"
                >
                    <span className="grid size-12 place-items-center rounded-2xl bg-white/10 text-amber-300">
                        <Phone className="size-6" aria-hidden />
                    </span>
                    <span className="text-lg font-semibold">Gọi hotline</span>
                    <span className="text-2xl font-bold tracking-wide tabular-nums">
                        {hotline}
                    </span>
                    <span className="text-sm text-white/70">
                        8:00 đến 21:00 mỗi ngày
                    </span>
                </a>
            </div>
        </div>
    );
}
