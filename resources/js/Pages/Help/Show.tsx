import { Head, Link } from '@inertiajs/react';
import { useState, type KeyboardEvent } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';

interface Step { title: string; text: string; icon: string; href: string | null }
interface Topic { key: string; title: string; icon: string; accent: string; summary: string; steps: Step[]; examples: { title: string; text: string }[]; faqs: { q: string; a: string }[] }

export default function HelpShow({ topic, others }: { topic: Topic; others: { key: string; title: string; icon: string }[] }) {
    const [active, setActive] = useState(0);
    const step = topic.steps[active];

    const onKey = (e: KeyboardEvent) => {
        if (e.key === 'ArrowRight') setActive((i) => Math.min(i + 1, topic.steps.length - 1));
        if (e.key === 'ArrowLeft') setActive((i) => Math.max(i - 1, 0));
    };

    return (
        <>
            <Head title={`${topic.title} · Help`} />
            <PageHeader icon={topic.icon} title={topic.title} description={topic.summary}
                actions={<Link href={route('help.index')} className="btn btn-light"><i className="bi bi-arrow-left me-1" />Help Center</Link>} />

            <SectionCard icon="diagram-3" title="How it works" subtitle="Click a step (or use ← →)" accent={topic.accent}>
                <div className="flow" role="tablist" aria-label={`${topic.title} steps`} onKeyDown={onKey} style={{ ['--accent' as string]: topic.accent }}>
                    {topic.steps.map((s, i) => (
                        <button key={s.title} type="button" role="tab" aria-selected={i === active} tabIndex={i === active ? 0 : -1}
                            className={`flow-step ${i === active ? 'active' : ''} ${i < active ? 'done' : ''}`} onClick={() => setActive(i)}>
                            <span className="flow-dot"><i className={`bi bi-${s.icon}`} /></span>
                            <span className="flow-num">Step {i + 1}</span>
                            <span className="flow-title">{s.title}</span>
                        </button>
                    ))}
                </div>
                {step && (
                    <div className="flow-detail" role="tabpanel" style={{ ['--accent' as string]: topic.accent }}>
                        <div className="d-flex align-items-start gap-3">
                            <span className="m-icon"><i className={`bi bi-${step.icon}`} /></span>
                            <div className="flex-grow-1">
                                <h3 className="h6 fw-bold mb-1">{step.title}</h3>
                                <p className="mb-2">{step.text}</p>
                                {step.href && (
                                    <a href={step.href} className="btn btn-sm btn-soft">
                                        Open this screen <i className="bi bi-box-arrow-up-right ms-1" />
                                    </a>
                                )}
                            </div>
                            <div className="d-flex gap-1">
                                <button type="button" className="btn btn-icon btn-light" disabled={active === 0} onClick={() => setActive(active - 1)} aria-label="Previous step"><i className="bi bi-chevron-left" /></button>
                                <button type="button" className="btn btn-icon btn-light" disabled={active === topic.steps.length - 1} onClick={() => setActive(active + 1)} aria-label="Next step"><i className="bi bi-chevron-right" /></button>
                            </div>
                        </div>
                    </div>
                )}
            </SectionCard>

            <div className="row g-4">
                {topic.examples.length > 0 && (
                    <div className="col-lg-7">
                        <SectionCard icon="lightbulb" title="Examples" accent="var(--chart-4)">
                            <div className="d-flex flex-column gap-3">
                                {topic.examples.map((e) => (
                                    <div key={e.title}>
                                        <strong className="d-block mb-1">{e.title}</strong>
                                        <span className="text-muted">{e.text}</span>
                                    </div>
                                ))}
                            </div>
                        </SectionCard>
                    </div>
                )}
                <div className={topic.examples.length ? 'col-lg-5' : 'col-12'}>
                    {topic.faqs.length > 0 && (
                        <SectionCard icon="question-circle" title="Questions" accent="var(--chart-2)">
                            {topic.faqs.map((f) => (
                                <details key={f.q} className="faq">
                                    <summary>{f.q}</summary>
                                    <p className="text-muted mb-0">{f.a}</p>
                                </details>
                            ))}
                        </SectionCard>
                    )}
                    {others.length > 0 && (
                        <SectionCard icon="collection" title="More help" accent="var(--chart-8)">
                            <div className="d-flex flex-wrap gap-2">
                                {others.map((o) => (
                                    <Link key={o.key} href={route('help.show', o.key)} className="btn btn-sm btn-light"><i className={`bi bi-${o.icon} me-1`} />{o.title}</Link>
                                ))}
                            </div>
                        </SectionCard>
                    )}
                </div>
            </div>
        </>
    );
}
