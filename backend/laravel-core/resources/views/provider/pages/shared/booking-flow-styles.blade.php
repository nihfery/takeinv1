@once
    @push('styles')
        <style>
            .ops-page { display: grid; gap: 16px; }
            .ops-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; }
            .ops-head h1 { margin: 0; font-size: 24px; letter-spacing: -0.03em; }
            .ops-head p { margin: 7px 0 0; color: var(--provider-muted); font-size: 13px; }
            .ops-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
            .ops-card { background: #fff; border: 1px solid var(--provider-border); border-radius: var(--provider-radius); box-shadow: 0 8px 18px rgba(15, 23, 42, .03); padding: 16px; }
            .ops-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
            .ops-metric { background: #fff; border: 1px solid var(--provider-border); border-radius: 16px; padding: 14px; }
            .ops-metric span { color: var(--provider-muted); font-size: 12px; font-weight: 700; }
            .ops-metric strong { display: block; margin-top: 8px; font-size: 24px; letter-spacing: -0.05em; }
            .ops-table-wrap { overflow-x: auto; margin-top: 12px; }
            .ops-table { width: 100%; border-collapse: collapse; min-width: 900px; }
            .ops-table th { background: #f9fafb; color: #475569; font-size: 12px; text-align: left; padding: 11px 12px; border: 1px solid var(--provider-border); }
            .ops-table td { padding: 12px; color: #374151; font-size: 13px; border: 1px solid var(--provider-border); vertical-align: top; }
            .ops-chip { display: inline-flex; align-items: center; min-height: 25px; padding: 0 9px; border-radius: 999px; background: #f3f4f6; color: #374151; font-size: 12px; font-weight: 800; text-transform: capitalize; }
            .ops-chip.success { background: #dcfce7; color: #166534; }
            .ops-chip.warn { background: #fef3c7; color: #92400e; }
            .ops-chip.info { background: #dbeafe; color: #1d4ed8; }
            .ops-chip.danger { background: #fee2e2; color: #991b1b; }
            .ops-form { display: grid; gap: 14px; }
            .ops-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
            .ops-field { display: grid; gap: 7px; color: #111827; font-size: 13px; font-weight: 750; }
            .ops-field input, .ops-field select, .ops-field textarea { width: 100%; min-height: 42px; border: 1px solid var(--provider-border); border-radius: 10px; padding: 0 12px; background: #fff; color: #111827; outline: 0; }
            .ops-field textarea { min-height: 92px; padding-top: 10px; resize: vertical; }
            .ops-check-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
            .ops-check { display: flex; align-items: center; gap: 8px; min-height: 38px; padding: 8px 10px; border: 1px solid var(--provider-border); border-radius: 10px; background: #fff; font-size: 12px; font-weight: 700; cursor: pointer; transition: border-color .18s ease, background .18s ease, box-shadow .18s ease; }
            .ops-check input { width: 15px; height: 15px; accent-color: #dc2626; cursor: pointer; }
            .ops-check.is-checked, .ops-check:has(input:checked) { border-color: #fecaca; background: #fff7f7; box-shadow: inset 0 0 0 1px #fecaca; }
            .ops-button { min-height: 36px; padding: 0 12px; border: 1px solid var(--provider-border); border-radius: 10px; background: #fff; color: #111827; display: inline-flex; align-items: center; justify-content: center; gap: 7px; font-size: 13px; font-weight: 800; text-decoration: none; cursor: pointer; }
            .ops-button.dark { background: #111827; border-color: #111827; color: #fff; }
            .ops-button.danger { border-color: #fecaca; color: #991b1b; }
            .ops-button.success { border-color: #bbf7d0; color: #166534; }
            .ops-button:disabled { opacity: .55; cursor: not-allowed; }
            .ops-row-actions { display: flex; flex-wrap: wrap; gap: 6px; }
            .ops-alert { padding: 12px 14px; border-radius: 12px; font-size: 13px; font-weight: 700; }
            .ops-alert.success { background: #ecfdf5; border: 1px solid #bbf7d0; color: #166534; }
            .ops-alert.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
            .ops-empty { padding: 24px; text-align: center; color: var(--provider-muted); border: 1px dashed var(--provider-border); border-radius: 14px; background: #fff; }
            .ops-empty.mini { grid-column: 1 / -1; padding: 14px; font-size: 12px; }
            .ops-staff-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
            .ops-staff-card { border: 1px solid var(--provider-border); border-radius: 14px; padding: 14px; background: #fff; }
            .ops-staff-card.is-updated { border-color: #86efac; box-shadow: 0 10px 28px rgba(22, 101, 52, .08); }
            .ops-staff-title { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 12px; }
            .ops-staff-card h3 { margin: 0 0 4px; font-size: 15px; }
            .ops-staff-card p { margin: 0; color: var(--provider-muted); font-size: 12px; }
            @media (max-width: 980px) {
                .ops-head, .ops-grid { grid-template-columns: 1fr; display: grid; align-items: start; }
                .ops-metrics, .ops-staff-grid, .ops-check-grid { grid-template-columns: 1fr; }
            }
        </style>
    @endpush
@endonce
