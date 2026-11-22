import { ConfirmAction } from '@/components/confirm-action';
import { Link } from '@inertiajs/react';
import { ReasonDialog } from '@/components/learning/actions';
import {
    LearningBadge,
    LearningProgress,
    Records,
} from '@/components/learning/records';
import type { EnrollmentRow } from '@/types/learning';
import { localTime } from '@/types/learning';

export function EnrollmentRecords({
    rows,
    directory = false,
}: {
    rows: EnrollmentRow[];
    directory?: boolean;
}) {
    return (
        <Records
            title="Ghi danh"
            rows={rows}
            rowKey={(row) => row.id}
            empty={
                directory
                    ? 'Không tìm thấy ghi danh.'
                    : 'Học viên chưa có ghi danh.'
            }
            columns={[
                ...(directory
                    ? [
                          {
                              label: 'Học viên',
                              cell: (row: EnrollmentRow) => (
                                  <Link
                                      href={row.student_url}
                                      className="font-semibold underline underline-offset-4"
                                  >
                                      {row.student ?? 'Xem hồ sơ học viên'}
                                  </Link>
                              ),
                          },
                      ]
                    : []),
                {
                    label: 'Khóa học',
                    cell: (row) => (
                        <>
                            <strong>{row.course ?? '—'}</strong>
                            <p className="text-muted-foreground">
                                {row.version === null
                                    ? '—'
                                    : `Phiên bản ${row.version}`}
                            </p>
                        </>
                    ),
                },
                {
                    label: 'Hiệu lực',
                    cell: (row) => (
                        <>
                            <LearningBadge
                                attention={row.validity !== 'Còn hạn'}
                            >
                                {row.validity}
                            </LearningBadge>
                            <p className="mt-1">
                                {row.expires_at
                                    ? localTime(row.expires_at)
                                    : 'Vĩnh viễn'}
                            </p>
                        </>
                    ),
                },
                { label: 'Trạng thái học', cell: (row) => row.status },
                {
                    label: 'Tiến độ',
                    cell: (row) =>
                        row.required ? (
                            <LearningProgress progress={row} />
                        ) : (
                            '—'
                        ),
                },
                {
                    label: 'Quyền học',
                    cell: (row) =>
                        row.revoke_url ? (
                            <ConfirmAction
                                size="sm"
                                form={{
                                    action: row.revoke_url,
                                    method: 'post',
                                }}
                                label="Thu hồi"
                                title={`Thu hồi quyền học "${row.course ?? ''}"?`}
                                description="Học viên mất quyền vào bài ngay; lượt quiz đang làm bị chốt. Hạn truy cập giữ nguyên để khôi phục sau."
                                confirmLabel="Thu hồi"
                                variant="destructive"
                                reasonField={{
                                    name: 'reason',
                                    label: 'Lý do',
                                    required: true,
                                }}
                                hidden={{ confirmed: '1' }}
                            />
                        ) : row.restore_url ? (
                            <ConfirmAction
                                size="sm"
                                form={{
                                    action: row.restore_url,
                                    method: 'post',
                                }}
                                label="Khôi phục"
                                title={`Khôi phục quyền học "${row.course ?? ''}"?`}
                                description="Hạn truy cập giữ như trước khi thu hồi; nếu hạn đã qua, ghi danh ở trạng thái hết hạn."
                                confirmLabel="Khôi phục"
                            />
                        ) : (
                            '—'
                        ),
                },
                ...(directory
                    ? [
                          {
                              label: 'Cấp thêm lượt',
                              cell: (row: EnrollmentRow) =>
                                  row.grants.length ? (
                                      <ul className="grid justify-items-start gap-2">
                                          {row.grants.map((grant) => (
                                              <li
                                                  key={grant.url}
                                                  className="max-w-full min-w-0"
                                              >
                                                  <ReasonDialog
                                                      url={grant.url}
                                                      label={grant.title}
                                                      subject={[
                                                          row.student,
                                                          row.course,
                                                          grant.title,
                                                      ]
                                                          .filter(Boolean)
                                                          .join(' · ')}
                                                  />
                                              </li>
                                          ))}
                                      </ul>
                                  ) : (
                                      <span className="text-muted-foreground">
                                          Không có quiz đủ điều kiện cấp thêm
                                          lượt.
                                      </span>
                                  ),
                          },
                      ]
                    : []),
            ]}
        />
    );
}
