import { useEffect, useRef } from 'react';
import type { EChartsOption } from 'echarts';
import { useResolvedTheme } from '@/hooks/useTheme';
import { cssVar } from '@/lib/format';

/** Theme-aware ECharts wrapper: colours come from the design tokens and follow light/dark live. */
export default function Chart({ option, height = 300, ariaLabel }: { option: EChartsOption; height?: number; ariaLabel: string }) {
    const ref = useRef<HTMLDivElement>(null);
    const theme = useResolvedTheme();

    useEffect(() => {
        let disposed = false;
        let chart: import('echarts').ECharts | undefined;
        let observer: ResizeObserver | undefined;

        import('echarts').then((echarts) => {
            if (disposed || !ref.current) return;
            chart = echarts.init(ref.current, undefined, { renderer: 'svg' });
            const palette = Array.from({ length: 8 }, (_, i) => cssVar(`--chart-${i + 1}`));
            const text = cssVar('--app-text-muted');
            const border = cssVar('--app-border');
            // Axis labels / lines default to ECharts' own greys: theme them unless the page overrides.
            const themeAxis = (axis: unknown) => {
                const style = { axisLabel: { color: text }, axisLine: { lineStyle: { color: border } }, splitLine: { lineStyle: { color: border } } };
                const apply = (a: Record<string, unknown>) => ({
                    ...a,
                    axisLabel: { ...style.axisLabel, ...(a.axisLabel as object) },
                    axisLine: { ...style.axisLine, ...(a.axisLine as object) },
                    splitLine: { ...style.splitLine, ...(a.splitLine as object) },
                });
                if (Array.isArray(axis)) return axis.map((a) => apply(a as Record<string, unknown>));
                return axis ? apply(axis as Record<string, unknown>) : axis;
            };
            chart.setOption({
                color: palette,
                textStyle: { fontFamily: cssVar('--app-font'), color: text },
                tooltip: { backgroundColor: cssVar('--app-surface-raised'), borderColor: border, textStyle: { color: cssVar('--app-text') } },
                legend: { textStyle: { color: text } },
                ...option,
                ...(option.xAxis ? { xAxis: themeAxis(option.xAxis) } : {}),
                ...(option.yAxis ? { yAxis: themeAxis(option.yAxis) } : {}),
            } as EChartsOption);
            observer = new ResizeObserver(() => chart?.resize());
            observer.observe(ref.current);
        });

        return () => {
            disposed = true;
            observer?.disconnect();
            chart?.dispose();
        };
    }, [option, theme]);

    return <div ref={ref} style={{ height, width: '100%' }} role="img" aria-label={ariaLabel} />;
}
