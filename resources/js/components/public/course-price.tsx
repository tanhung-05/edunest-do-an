export function CoursePrice({
    price,
    originalPrice,
}: {
    price: number;
    originalPrice: number | null;
}) {
    return (
        <p className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <span className="font-bold tabular-nums">
                <span className="sr-only">Giá bán </span>
                {price.toLocaleString('vi-VN')}đ
            </span>
            {originalPrice !== null && (
                <span className="text-sm text-muted-foreground">
                    <span className="sr-only">Giá gốc </span>
                    <s>{originalPrice.toLocaleString('vi-VN')}đ</s>
                </span>
            )}
        </p>
    );
}
