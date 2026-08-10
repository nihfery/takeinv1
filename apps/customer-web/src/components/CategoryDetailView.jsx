'use client';

import Link from 'next/link';
import {
    ArrowLeft,
    ArrowRight,
    Leaf,
    Scissors,
    ShieldCheck,
    Sparkles,
    Store,
} from 'lucide-react';
import { FreshNavigation } from './LandingPage.jsx';
import { getCategoryPath } from '../lib/service-taxonomy.js';

function categoryIcon(slug) {
    return ({
        nail: Sparkles,
        wellness: ShieldCheck,
        beauty: Leaf,
        'hair-salon': Scissors,
    })[slug] || Store;
}

function searchPath(category, subcategory) {
    const query = new URLSearchParams();
    query.set('service', subcategory.name);
    query.set('category', category.slug);
    query.set('subcategory', subcategory.slug);
    if (category.id) query.set('category_id', category.id);
    if (subcategory.id) query.set('subcategory_id', subcategory.id);
    return `/search?${query.toString()}`;
}

export function CategoryDetailView({ category, taxonomy = [] }) {
    const CategoryIcon = categoryIcon(category.slug);
    const subcategories = Array.isArray(category.children) ? category.children : [];

    return (
        <div className="fresh-landing category-route-shell">
            <FreshNavigation providerUrl="/provider" customerAppUrl="/" />

            <main className="category-detail-page">
                <section className={`category-detail-hero category-tone-${category.slug}`}>
                    <nav className="category-breadcrumbs" aria-label="Breadcrumb">
                        <Link href="/">Home</Link>
                        <span aria-hidden="true">/</span>
                        <span>{category.name}</span>
                    </nav>

                    <div className="category-detail-hero-content">
                        <span className="category-detail-hero-icon" aria-hidden="true">
                            <CategoryIcon size={34} strokeWidth={1.7} />
                        </span>
                        <div>
                            <span className="category-detail-eyebrow">Service category</span>
                            <h1>Explore {category.name}</h1>
                            <p>{category.description || `Choose from our available ${category.name.toLowerCase()} services.`}</p>
                        </div>
                    </div>

                    <div className="category-detail-count">
                        <strong>{subcategories.length}</strong>
                        <span>subcategories available</span>
                    </div>
                </section>

                <section className="subcategory-section" aria-labelledby="subcategory-title">
                    <div className="subcategory-section-heading">
                        <div>
                            <span>Choose a service</span>
                            <h2 id="subcategory-title">What would you like to book?</h2>
                        </div>
                        <Link href="/" className="subcategory-back-link">
                            <ArrowLeft size={16} />
                            Back to home
                        </Link>
                    </div>

                    <div className="subcategory-grid">
                        {subcategories.map((subcategory, index) => (
                            <Link
                                className={`subcategory-card tone-${(index % 4) + 1}`}
                                href={searchPath(category, subcategory)}
                                key={subcategory.id || subcategory.slug}
                            >
                                <span className="subcategory-card-number" aria-hidden="true">
                                    {String(index + 1).padStart(2, '0')}
                                </span>
                                <span className="subcategory-card-icon" aria-hidden="true">
                                    <CategoryIcon size={24} strokeWidth={1.7} />
                                </span>
                                <span className="subcategory-card-copy">
                                    <small>Subcategory</small>
                                    <strong>{subcategory.name}</strong>
                                    <span>{subcategory.description || `Find salons offering ${subcategory.name.toLowerCase()} services.`}</span>
                                </span>
                                <span className="subcategory-card-action">
                                    View salons
                                    <ArrowRight size={17} />
                                </span>
                            </Link>
                        ))}
                    </div>
                </section>

                <nav className="other-category-menu" aria-label="Other service categories">
                    <span>Explore other categories</span>
                    <div>
                        {taxonomy.map((item) => {
                            const ItemIcon = categoryIcon(item.slug);
                            const active = item.slug === category.slug;

                            return (
                                <Link
                                    aria-current={active ? 'page' : undefined}
                                    className={active ? 'is-active' : ''}
                                    href={getCategoryPath(item)}
                                    key={item.id || item.slug}
                                >
                                    <ItemIcon size={17} />
                                    {item.name}
                                </Link>
                            );
                        })}
                    </div>
                </nav>
            </main>
        </div>
    );
}
