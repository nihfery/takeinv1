import { notFound } from 'next/navigation';
import { CategoryDetailView } from '../../../src/components/CategoryDetailView.jsx';
import { getServiceCategory } from '../../../src/lib/landing-data.js';

export const dynamic = 'force-dynamic';

export async function generateMetadata({ params }) {
    const { categorySlug } = await params;
    const { category } = await getServiceCategory(categorySlug);

    if (!category) {
        return { title: 'Category not found | YouYaku' };
    }

    return {
        title: `${category.name} services | YouYaku`,
        description: category.description || `Explore and book ${category.name.toLowerCase()} services on YouYaku.`,
    };
}
export default async function CategoryPage({ params }) {
    const { categorySlug } = await params;
    const { category, taxonomy } = await getServiceCategory(categorySlug);

    if (!category) notFound();

    return <CategoryDetailView category={category} taxonomy={taxonomy} />;
}
