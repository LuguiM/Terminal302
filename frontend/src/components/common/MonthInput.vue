<script setup>
import { computed, ref, watch } from "vue";

defineOptions({ inheritAttrs: false });

const props = defineProps({
  modelValue: {
    type: String,
    default: "",
  },
  label: {
    type: String,
    default: "Mes",
  },
});

const emit = defineEmits(["update:modelValue"]);

const menu = ref(false);
const pickerYear = ref(new Date().getFullYear());
const pickerMonth = ref(new Date().getMonth());

const selectedYear = computed(() => {
  const year = Number(props.modelValue?.slice(0, 4));

  return Number.isInteger(year) ? year : new Date().getFullYear();
});

const selectedMonth = computed(() => {
  const month = Number(props.modelValue?.slice(5, 7)) - 1;

  return month >= 0 && month <= 11 ? month : new Date().getMonth();
});

const displayValue = computed(() => {
  if (!/^\d{4}-\d{2}$/.test(props.modelValue)) {
    return "";
  }

  const date = new Date(selectedYear.value, selectedMonth.value, 1);
  const month = new Intl.DateTimeFormat("es-SV", { month: "long" }).format(
    date,
  );

  return `${month.charAt(0).toUpperCase()}${month.slice(1)} ${selectedYear.value}`;
});

watch(
  () => props.modelValue,
  () => {
    pickerYear.value = selectedYear.value;
    pickerMonth.value = selectedMonth.value;
  },
  { immediate: true },
);

function selectMonth(month) {
  pickerMonth.value = Number(month);
  emit(
    "update:modelValue",
    `${pickerYear.value}-${String(pickerMonth.value + 1).padStart(2, "0")}`,
  );
  menu.value = false;
}
</script>

<template>
  <v-menu
    v-model="menu"
    :close-on-content-click="false"
    location="bottom start"
  >
    <template #activator="{ props: activatorProps }">
      <v-text-field
        v-bind="{ ...activatorProps, ...$attrs }"
        :label="label"
        :model-value="displayValue"
        readonly
      />
    </template>

    <v-date-picker
      :month="pickerMonth"
      :year="pickerYear"
      view-mode="months"
      @update:month="selectMonth"
      @update:year="pickerYear = Number($event)"
    />
  </v-menu>
</template>
