<script setup lang="ts">
defineProps<{
    kind: string;
}>();
</script>

<template>
    <svg v-if="kind === 'cedula'" viewBox="0 0 160 100" fill="none" class="h-full w-full" aria-hidden="true">
        <rect x="4" y="8" width="152" height="84" rx="8" fill="#F4D35E" />
        <rect x="4" y="8" width="152" height="14" rx="8" fill="#1A365D" />
        <rect x="4" y="18" width="152" height="8" fill="#1A365D" />
        <rect x="14" y="36" width="38" height="46" rx="3" fill="#E8C547" stroke="#8B6914" stroke-width="1.5" />
        <circle cx="33" cy="52" r="8" fill="#8B6914" />
        <path d="M20 76c3-10 23-10 26 0" stroke="#8B6914" stroke-width="2" fill="none" />
        <rect x="62" y="38" width="82" height="6" rx="2" fill="#8B6914" />
        <rect x="62" y="50" width="64" height="5" rx="2" fill="#C4A035" />
        <rect x="62" y="60" width="52" height="5" rx="2" fill="#C4A035" />
        <rect x="62" y="70" width="40" height="5" rx="2" fill="#C4A035" />
    </svg>

    <svg v-else-if="kind === 'licencia'" viewBox="0 0 160 100" fill="none" class="h-full w-full" aria-hidden="true">
        <rect x="4" y="8" width="152" height="84" rx="8" fill="#F4A6C8" />
        <rect x="4" y="8" width="152" height="16" rx="8" fill="#9D174D" />
        <rect x="4" y="20" width="152" height="6" fill="#9D174D" />
        <text x="14" y="20" fill="#fff" font-size="8" font-weight="700">LICENCIA</text>
        <rect x="14" y="36" width="38" height="46" rx="3" fill="#FCE7F3" stroke="#9D174D" stroke-width="1.5" />
        <circle cx="33" cy="52" r="8" fill="#9D174D" />
        <path d="M20 76c3-10 23-10 26 0" stroke="#9D174D" stroke-width="2" fill="none" />
        <rect x="62" y="38" width="78" height="6" rx="2" fill="#9D174D" />
        <rect x="62" y="50" width="60" height="5" rx="2" fill="#BE185D" opacity=".7" />
        <rect x="62" y="62" width="28" height="16" rx="3" fill="#9D174D" />
        <text x="68" y="73" fill="#fff" font-size="9" font-weight="700">A2</text>
    </svg>

    <svg v-else-if="kind === 'tarjeta'" viewBox="0 0 160 100" fill="none" class="h-full w-full" aria-hidden="true">
        <rect x="4" y="8" width="152" height="84" rx="8" fill="#86EFAC" />
        <rect x="4" y="8" width="152" height="16" rx="8" fill="#166534" />
        <rect x="4" y="20" width="152" height="6" fill="#166534" />
        <text x="14" y="20" fill="#fff" font-size="7.5" font-weight="700">PROPIEDAD</text>
        <path d="M22 72c6-16 18-22 34-18 4 8 10 12 18 12h8l6-10 12 2-4 12H36l-6 8H20z" fill="#166534" />
        <circle cx="40" cy="76" r="7" fill="#14532D" stroke="#BBF7D0" stroke-width="2" />
        <circle cx="78" cy="76" r="7" fill="#14532D" stroke="#BBF7D0" stroke-width="2" />
        <rect x="100" y="40" width="42" height="10" rx="2" fill="#166534" />
        <rect x="100" y="56" width="32" height="6" rx="2" fill="#15803D" />
        <rect x="100" y="68" width="24" height="6" rx="2" fill="#15803D" />
    </svg>

    <svg v-else-if="kind === 'photo'" viewBox="0 0 120 150" fill="none" class="h-full w-full" aria-hidden="true">
        <rect x="6" y="6" width="108" height="138" rx="10" fill="#1E293B" stroke="#F59E0B" stroke-width="3" />
        <circle cx="60" cy="58" r="22" fill="#F59E0B" />
        <path d="M24 128c6-28 66-28 72 0" fill="#F59E0B" />
    </svg>

    <svg v-else-if="kind === 'soat'" viewBox="0 0 140 100" fill="none" class="h-full w-full" aria-hidden="true">
        <rect x="10" y="8" width="120" height="84" rx="6" fill="#DBEAFE" />
        <rect x="10" y="8" width="120" height="20" rx="6" fill="#1D4ED8" />
        <rect x="10" y="22" width="120" height="8" fill="#1D4ED8" />
        <text x="22" y="22" fill="#fff" font-size="10" font-weight="700">SOAT</text>
        <path d="M70 40l14 6v16c0 10-8 16-14 20-6-4-14-10-14-20V46z" fill="#1D4ED8" />
        <rect x="24" y="78" width="52" height="6" rx="2" fill="#3B82F6" />
    </svg>

    <svg v-else-if="kind === 'tecnomecanica'" viewBox="0 0 140 100" fill="none" class="h-full w-full" aria-hidden="true">
        <rect x="10" y="8" width="120" height="84" rx="6" fill="#FFEDD5" />
        <rect x="10" y="8" width="120" height="20" rx="6" fill="#C2410C" />
        <rect x="10" y="22" width="120" height="8" fill="#C2410C" />
        <text x="20" y="22" fill="#fff" font-size="8" font-weight="700">TECNOMEC.</text>
        <circle cx="70" cy="62" r="16" stroke="#C2410C" stroke-width="5" fill="none" />
        <path d="M70 46v8M70 70v8M54 62h8M78 62h8M58 50l6 6M76 68l6 6M58 74l6-6M76 56l6-6" stroke="#C2410C" stroke-width="3" stroke-linecap="round" />
    </svg>

    <svg v-else viewBox="0 0 160 100" fill="none" class="h-full w-full" aria-hidden="true">
        <rect x="8" y="12" width="144" height="76" rx="8" stroke="#F59E0B" stroke-width="3" />
    </svg>
</template>
