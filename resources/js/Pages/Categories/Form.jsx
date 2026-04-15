import React, { useEffect, useRef, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/inertia-react';

export default function Form({ mode, category, routes }) {
    const isEdit = mode === 'edit';
    const [suggestions, setSuggestions] = useState([]);
    const [showSuggestions, setShowSuggestions] = useState(false);
    const [isTyping, setIsTyping] = useState(false);
    const wrapperRef = useRef(null);
    const suppressNextFetchRef = useRef(false);

    const { data, setData, post, put, processing, errors } = useForm({
        name: category?.name || '',
        description: category?.description || '',
    });

    useEffect(() => {
        if (suppressNextFetchRef.current) {
            suppressNextFetchRef.current = false;
            return undefined;
        }

        if (!isTyping) {
            return undefined;
        }

        if (!data.name || data.name.trim().length < 1) {
            setSuggestions([]);
            setShowSuggestions(false);
            return;
        }

        const timer = setTimeout(async () => {
            const response = await window.axios.get(routes.autocomplete, {
                params: { term: data.name },
            });
            const nextSuggestions = response.data?.results || [];
            setSuggestions(nextSuggestions);
            setShowSuggestions(nextSuggestions.length > 0);
        }, 300);

        return () => clearTimeout(timer);
    }, [data.name, isTyping, routes.autocomplete]);

    useEffect(() => {
        const handleOutsideClick = (event) => {
            if (wrapperRef.current && !wrapperRef.current.contains(event.target)) {
                setShowSuggestions(false);
            }
        };

        document.addEventListener('mousedown', handleOutsideClick);
        return () => document.removeEventListener('mousedown', handleOutsideClick);
    }, []);

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(routes.update);
            return;
        }
        post(routes.store);
    };

    return (
        <>
            <Head title={isEdit ? 'Edit Packaging' : 'Add Packaging'} />

            <div className="page-header">
                <div className="row">
                    <div className="col-sm-12">
                        <h3 className="page-title">{isEdit ? 'Edit Packaging' : 'Add Packaging'}</h3>
                        <ul className="breadcrumb">
                            <li className="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li className="breadcrumb-item active">{isEdit ? 'Edit Packaging' : 'Add Packaging'}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div className="row">
                <div className="col-sm-12">
                    <div className="card">
                        <div className="card-body custom-edit-service">
                            <form onSubmit={submit}>
                                <div className="service-fields mb-3">
                                    <div className="row">
                                        <div className="col-lg-6">
                                            <div className="form-group">
                                                <label>Name <span className="text-danger">*</span></label>
                                                <div className="position-relative" ref={wrapperRef}>
                                                <input
                                                    className="form-control"
                                                    type="text"
                                                    value={data.name}
                                                    onChange={(e) => {
                                                        setIsTyping(true);
                                                        setData('name', e.target.value);
                                                    }}
                                                    placeholder="Search or type packaging name..."
                                                />
                                                {errors.name && <small className="text-danger">{errors.name}</small>}
                                                {showSuggestions && suggestions.length > 0 && (
                                                    <ul className="list-group position-absolute w-100" style={{ zIndex: 20 }}>
                                                        {suggestions.map((item, idx) => (
                                                            <li
                                                                key={`${item.id}-${idx}`}
                                                                className="list-group-item list-group-item-action"
                                                                onClick={() => {
                                                                    suppressNextFetchRef.current = true;
                                                                    setIsTyping(false);
                                                                    setData('name', item.text);
                                                                    setSuggestions([]);
                                                                    setShowSuggestions(false);
                                                                }}
                                                            >
                                                                {item.text}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                )}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="service-fields mb-3">
                                    <div className="row">
                                        <div className="col-lg-12">
                                            <div className="form-group">
                                                <label>Description</label>
                                                <textarea
                                                    className="form-control service-desc"
                                                    value={data.description}
                                                    onChange={(e) => setData('description', e.target.value)}
                                                />
                                                {errors.description && <small className="text-danger">{errors.description}</small>}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="submit-section">
                                    <button className="btn btn-success submit-btn mr-2" disabled={processing} type="submit">
                                        {processing ? 'Saving...' : 'Submit'}
                                    </button>
                                    <Link href={routes.index} className="btn btn-secondary">Back</Link>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
