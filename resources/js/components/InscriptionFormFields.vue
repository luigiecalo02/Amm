<script setup lang="ts">
import { computed, watch } from 'vue';
import FormField from '@/components/FormField.vue';

const props = defineProps<{
    form: Record<string, any>;
    filled?: string[];
    pilots?: { id: string; name: string; document_number?: string | null }[];
    role?: string;
    step: string;
}>();

const mark = (key: string) => (props.filled ?? []).includes(key);
const isPassenger = computed(() => props.role === 'passenger');
const show = (key: string) => props.step === 'all' || props.step === key;

const addCategory = () => {
    if (!Array.isArray(props.form.license_categories)) {
        props.form.license_categories = [];
    }
    props.form.license_categories.push({ category: '', expires_at: '' });
};

watch(
    () => props.form.metadata?.baptized,
    (baptized) => {
        if (!baptized && props.form.metadata) {
            props.form.metadata.baptized_at = '';
        }
    },
);
</script>

<template>
    <section v-show="show('identity')" class="panel grid gap-3 p-5 md:grid-cols-2">
        <h2 class="font-display text-xl md:col-span-2">Identidad</h2>
        <FormField label="Nombres">
            <input v-model="form.first_name" class="field" :class="{ 'ring-1 ring-ember-400': mark('first_name') }" />
        </FormField>
        <FormField label="Segundo nombre">
            <input v-model="form.middle_name" class="field" :class="{ 'ring-1 ring-ember-400': mark('middle_name') }" />
        </FormField>
        <FormField label="Primer apellido">
            <input v-model="form.last_name" class="field" :class="{ 'ring-1 ring-ember-400': mark('last_name') }" />
        </FormField>
        <FormField label="Segundo apellido">
            <input v-model="form.second_last_name" class="field" :class="{ 'ring-1 ring-ember-400': mark('second_last_name') }" />
        </FormField>
        <FormField label="Tipo de documento">
            <select v-model="form.document_type" class="field">
                <option value="cc">Cédula de ciudadanía</option>
                <option value="ti">Tarjeta de identidad</option>
                <option value="ce">Cédula de extranjería</option>
                <option value="passport">Pasaporte</option>
            </select>
        </FormField>
        <FormField label="Número de documento">
            <input v-model="form.document_number" class="field" :class="{ 'ring-1 ring-ember-400': mark('document_number') }" />
        </FormField>
        <FormField label="Expedida en">
            <input v-model="form.document_issued_in" class="field" />
        </FormField>
    </section>

    <section v-show="show('contact')" class="panel grid gap-3 p-5 md:grid-cols-2">
        <h2 class="font-display text-xl md:col-span-2">Contacto y residencia</h2>
        <FormField label="Fecha de nacimiento">
            <input v-model="form.birth_date" type="date" class="field" :class="{ 'ring-1 ring-ember-400': mark('birth_date') }" />
        </FormField>
        <FormField label="Municipio de nacimiento">
            <input v-model="form.birth_city" class="field" />
        </FormField>
        <FormField label="Departamento de nacimiento">
            <input v-model="form.birth_department" class="field" />
        </FormField>
        <FormField label="Sexo">
            <select v-model="form.gender" class="field" :class="{ 'ring-1 ring-ember-400': mark('gender') }">
                <option value="">Seleccione</option>
                <option value="masculino">Masculino</option>
                <option value="femenino">Femenino</option>
            </select>
        </FormField>
        <FormField label="Estado civil">
            <select v-model="form.marital_status" class="field">
                <option value="soltero">Soltero</option>
                <option value="casado">Casado</option>
                <option value="union_libre">Unión libre</option>
                <option value="viudo">Viudo</option>
            </select>
        </FormField>
        <FormField label="Correo">
            <input v-model="form.email" type="email" class="field" />
        </FormField>
        <FormField label="Teléfono">
            <input v-model="form.phone" class="field" />
        </FormField>
        <FormField label="Dirección de residencia" span>
            <input v-model="form.address" class="field" />
        </FormField>
        <FormField label="Barrio">
            <input v-model="form.neighborhood" class="field" />
        </FormField>
        <FormField label="Municipio">
            <input v-model="form.city" class="field" />
        </FormField>
        <FormField label="Departamento">
            <input v-model="form.department" class="field" />
        </FormField>
    </section>

    <section v-show="show('church')" class="panel grid gap-3 p-5 md:grid-cols-2">
        <h2 class="font-display text-xl md:col-span-2">Iglesia</h2>
        <FormField label="Iglesia a la que pertenece">
            <input v-model="form.metadata.church_name" class="field" />
        </FormField>
        <FormField label="Distrito">
            <input v-model="form.metadata.church_district" class="field" />
        </FormField>
        <FormField label="Cargo en la iglesia">
            <input v-model="form.metadata.church_role" class="field" />
        </FormField>
        <FormField label="Bautizado">
            <select v-model="form.metadata.baptized" class="field">
                <option :value="true">Sí</option>
                <option :value="false">No</option>
            </select>
        </FormField>
        <FormField v-if="form.metadata.baptized" label="Fecha de bautismo">
            <input v-model="form.metadata.baptized_at" type="date" class="field" />
        </FormField>
    </section>

    <section v-show="show('health')" class="panel grid gap-3 p-5 md:grid-cols-2">
        <h2 class="font-display text-xl md:col-span-2">Salud</h2>
        <FormField label="EPS">
            <input v-model="form.health.eps" class="field" />
        </FormField>
        <FormField label="Tipo de sangre">
            <select v-model="form.health.blood_type" class="field" :class="{ 'ring-1 ring-ember-400': mark('blood_type') }">
                <option value="">Seleccione</option>
                <option value="O">O</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="AB">AB</option>
            </select>
        </FormField>
        <FormField label="RH">
            <select v-model="form.health.rh" class="field" :class="{ 'ring-1 ring-ember-400': mark('rh') }">
                <option value="">Seleccione</option>
                <option value="positivo">Positivo</option>
                <option value="negativo">Negativo</option>
            </select>
        </FormField>
        <FormField label="Peso (kg)">
            <input v-model="form.health.weight_kg" type="number" step="0.1" class="field" />
        </FormField>
        <FormField label="Altura (cm)">
            <input v-model="form.health.height_cm" type="number" step="0.1" class="field" :class="{ 'ring-1 ring-ember-400': mark('height_cm') }" />
        </FormField>
        <FormField label="¿Está realizando algún tratamiento especial?" span>
            <input v-model="form.health.special_treatment" class="field" />
        </FormField>
        <FormField label="¿Está tomando algún medicamento especial?" span>
            <input v-model="form.health.medications" class="field" />
        </FormField>
        <FormField label="¿Tiene alguna alergia?" span>
            <input v-model="form.health.allergies" class="field" />
        </FormField>
        <h3 class="font-display text-lg md:col-span-2">Contacto de emergencia</h3>
        <FormField label="Nombre">
            <input v-model="form.emergency_contact.name" class="field" />
        </FormField>
        <FormField label="Teléfono">
            <input v-model="form.emergency_contact.phone" class="field" />
        </FormField>
        <FormField label="Dirección">
            <input v-model="form.emergency_contact.address" class="field" />
        </FormField>
        <FormField label="Parentesco">
            <input v-model="form.emergency_contact.relationship" class="field" />
        </FormField>
    </section>

    <section v-show="show('pilot') && isPassenger" class="panel grid gap-3 p-5 md:grid-cols-2">
        <h2 class="font-display text-xl md:col-span-2">Piloto</h2>
        <p class="text-sm text-cream/60 md:col-span-2">
            Al completar el formulario debes elegir un piloto ya inscrito en el club.
        </p>
        <FormField label="Piloto" span>
            <select v-model="form.pilot_person_id" class="field">
                <option value="">Selecciona un piloto</option>
                <option v-for="pilot in pilots ?? []" :key="pilot.id" :value="pilot.id">
                    {{ pilot.name }}{{ pilot.document_number ? ` · ${pilot.document_number}` : '' }}
                </option>
            </select>
        </FormField>
        <p v-if="!(pilots ?? []).length" class="text-sm text-amber-200 md:col-span-2">
            Aún no hay pilotos registrados en este club. Pide al club que inscriba primero al piloto.
        </p>
    </section>

    <section v-show="show('license') && !isPassenger" class="panel grid gap-3 p-5 md:grid-cols-2">
        <h2 class="font-display text-xl md:col-span-2">Licencia de conducción</h2>
        <FormField label="Identificación de la licencia">
            <input v-model="form.license_document_number" class="field" :class="{ 'ring-1 ring-ember-400': mark('license_document_number') }" />
        </FormField>
        <FormField label="Vigencia general">
            <input v-model="form.license_expiration" type="date" class="field" :class="{ 'ring-1 ring-ember-400': mark('license_expiration') }" />
        </FormField>
        <div class="md:col-span-2 space-y-2">
            <p class="text-sm text-cream/60">Categorías y vigencia de cada una</p>
            <div
                v-for="(category, index) in form.license_categories"
                :key="index"
                class="grid gap-2 md:grid-cols-2"
            >
                <input v-model="category.category" class="field" placeholder="A2" :class="{ 'ring-1 ring-ember-400': mark('license_categories') }" />
                <input v-model="category.expires_at" type="date" class="field" :class="{ 'ring-1 ring-ember-400': mark('license_categories') }" />
            </div>
            <button type="button" class="btn-ghost" @click="addCategory">Añadir categoría</button>
        </div>
    </section>

    <section v-show="show('vehicle') && !isPassenger" class="panel grid gap-3 p-5 md:grid-cols-2">
        <h2 class="font-display text-xl md:col-span-2">Vehículo</h2>
        <FormField label="Tipo de vehículo">
            <select v-model="form.motorcycle.vehicle_type" class="field">
                <option value="motorcycle">Moto</option>
                <option value="car">Carro</option>
            </select>
        </FormField>
        <FormField label="Rol en el club">
            <input type="hidden" v-model="form.vehicle_role" />
            <select v-model="form.vehicle_role" class="field" disabled>
                <option value="driver">Piloto</option>
                <option value="passenger">Copiloto</option>
                <option value="both">Ambos</option>
            </select>
        </FormField>
        <FormField label="Marca">
            <input v-model="form.motorcycle.brand" class="field" :class="{ 'ring-1 ring-ember-400': mark('brand') }" />
        </FormField>
        <FormField label="Línea">
            <input v-model="form.motorcycle.line" class="field" :class="{ 'ring-1 ring-ember-400': mark('line') }" />
        </FormField>
        <FormField label="Modelo (año)">
            <input v-model="form.motorcycle.year" type="number" class="field" :class="{ 'ring-1 ring-ember-400': mark('year') }" />
        </FormField>
        <FormField label="Color">
            <input v-model="form.motorcycle.color" class="field" :class="{ 'ring-1 ring-ember-400': mark('color') }" />
        </FormField>
        <FormField label="Cilindraje">
            <input v-model="form.motorcycle.engine_displacement" type="number" class="field" />
        </FormField>
        <FormField label="Placa">
            <input v-model="form.motorcycle.plate" class="field" :class="{ 'ring-1 ring-ember-400': mark('plate') }" />
        </FormField>
        <FormField label="Propietario">
            <input v-model="form.motorcycle.owner_name" class="field" :class="{ 'ring-1 ring-ember-400': mark('owner_name') }" />
        </FormField>
        <FormField label="Identificación del propietario">
            <input v-model="form.motorcycle.owner_document_number" class="field" :class="{ 'ring-1 ring-ember-400': mark('owner_document_number') }" />
        </FormField>
        <FormField label="Vence SOAT">
            <input v-model="form.soat_expiration" type="date" class="field" :class="{ 'ring-1 ring-ember-400': mark('soat_expiration') }" />
        </FormField>
        <FormField label="Vence tecnomecánica">
            <input v-model="form.tecnomecanica_expiration" type="date" class="field" :class="{ 'ring-1 ring-ember-400': mark('tecnomecanica_expiration') }" />
        </FormField>
    </section>
</template>
