import { useState } from 'react';
import { selectClass } from '@/components/page';
import type { TeacherOption } from '@/types/course';

/**
 * CR-259 (UAT 22/11): chọn người soạn theo chuyên môn. Giáo viên đúng môn của khóa đứng đầu;
 * chọn người khác môn vẫn được nhưng hiện cảnh báo (không chặn, vì Giáo vụ có thể chủ ý phân công).
 */
export function AuthorSelect({
    teachers,
    categoryId,
    defaultValue = '',
    required = false,
}: {
    teachers: TeacherOption[];
    categoryId: number | null;
    defaultValue?: number | string;
    required?: boolean;
}) {
    const [author, setAuthor] = useState(String(defaultValue));
    const fits = (t: TeacherOption) =>
        categoryId !== null && t.subject_ids.includes(categoryId);
    const matching = teachers.filter(fits);
    const others = teachers.filter((t) => !fits(t));
    const chosen = teachers.find((t) => String(t.id) === author);

    let warning: string | null = null;

    if (chosen && categoryId !== null && !fits(chosen)) {
        warning =
            chosen.subject_ids.length === 0
                ? `${chosen.name} chưa được ghi chuyên môn. Có thể cập nhật ở trang Tài khoản giáo viên.`
                : `${chosen.name} không phụ trách môn của khóa này. Vẫn giao được nếu bạn chủ ý phân công.`;
    }

    const option = (t: TeacherOption) => (
        <option key={t.id} value={t.id}>
            {t.name}
        </option>
    );

    return (
        <>
            <select
                id="author_id"
                name="author_id"
                required={required}
                value={author}
                onChange={(e) => setAuthor(e.target.value)}
                className={selectClass}
                aria-describedby={warning ? 'author-warning' : undefined}
            >
                <option value="">Chọn</option>
                {categoryId === null ? (
                    teachers.map(option)
                ) : (
                    <>
                        {matching.length > 0 && (
                            <optgroup label="Đúng chuyên môn">
                                {matching.map(option)}
                            </optgroup>
                        )}
                        <optgroup
                            label={
                                matching.length > 0
                                    ? 'Giáo viên khác'
                                    : 'Chưa có giáo viên ghi chuyên môn này'
                            }
                        >
                            {others.map(option)}
                        </optgroup>
                    </>
                )}
            </select>
            {warning && (
                <p
                    id="author-warning"
                    role="status"
                    className="text-sm text-amber-800 dark:text-amber-300"
                >
                    {warning}
                </p>
            )}
        </>
    );
}
