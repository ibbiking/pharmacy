export default function SkeletonRows({ rows = 6, cols = 4 }: { rows?: number; cols?: number }) {
    return (
        <>
            {Array.from({ length: rows }).map((_, r) => (
                <tr key={r}>
                    {Array.from({ length: cols }).map((__, c) => (
                        <td key={c}>
                            <div className="skeleton" style={{ width: `${50 + ((r * 7 + c * 13) % 45)}%` }} />
                        </td>
                    ))}
                </tr>
            ))}
        </>
    );
}
