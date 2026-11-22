import { Form, Head } from '@inertiajs/react';
import { Block, Field, Page, textareaClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { update } from '@/routes/teacher/profile';

type Props = {
    profile: {
        bio: string | null;
        achievements: string | null;
        photo: string | null;
    } | null;
    publicUrl: string;
};

export default function TeacherProfile({ profile, publicUrl }: Props) {
    return (
        <Page
            title="Hồ sơ giáo viên của tôi"
            description="Nội dung hiện trên trang giáo viên công khai."
            actions={
                <a
                    href={publicUrl}
                    target="_blank"
                    rel="noreferrer"
                    className="text-sm font-medium underline"
                >
                    Xem trang công khai
                </a>
            }
        >
            <Head title="Hồ sơ giáo viên" />
            {!profile ? (
                <Block tone="accent">
                    <p className="font-semibold">
                        Chưa có hồ sơ. Giáo vụ sẽ lập hồ sơ cho bạn.
                    </p>
                </Block>
            ) : (
                <Form
                    {...update.form()}
                    options={{ preserveScroll: true }}
                    className="grid max-w-2xl gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Ảnh"
                                htmlFor="photo"
                                hint="Ảnh JPG/PNG, tối đa 2 MB."
                                error={errors.photo}
                            >
                                <div className="flex items-center gap-4">
                                    {profile.photo && (
                                        <img
                                            src={profile.photo}
                                            alt=""
                                            className="size-20 rounded-lg object-cover"
                                        />
                                    )}
                                    <Input
                                        id="photo"
                                        name="photo"
                                        type="file"
                                        accept="image/*"
                                    />
                                </div>
                            </Field>
                            <Field
                                label="Giới thiệu"
                                htmlFor="bio"
                                error={errors.bio}
                            >
                                <textarea
                                    id="bio"
                                    name="bio"
                                    maxLength={5000}
                                    defaultValue={profile.bio ?? ''}
                                    className={textareaClass}
                                />
                            </Field>
                            <Field
                                label="Thành tích"
                                htmlFor="achievements"
                                error={errors.achievements}
                            >
                                <textarea
                                    id="achievements"
                                    name="achievements"
                                    maxLength={5000}
                                    defaultValue={profile.achievements ?? ''}
                                    className={textareaClass}
                                />
                            </Field>
                            <div>
                                <Button type="submit" disabled={processing}>
                                    Lưu hồ sơ
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            )}
        </Page>
    );
}
