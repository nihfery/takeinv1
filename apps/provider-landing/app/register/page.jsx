'use client';

import dynamic from 'next/dynamic';

const Register = dynamic(() => import('../../src/Register.jsx'), { ssr: false });

export default function Page() {
  return <Register />;
}
