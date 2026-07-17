<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?:
            | "table"
            | "form"
            | "dashboard"
            | "desk"
            | "detail"
            | "reference"
            | "reference-overview";
    }>(),
    { variant: "table" },
);
</script>

<template>
    <div aria-hidden="true">
        <div class="mb-7 flex items-end justify-between gap-4">
            <div class="min-w-0 flex-1">
                <div class="dw-skeleton h-3 w-28"></div>
                <div class="dw-skeleton mt-3 h-8 w-72 max-w-[75%]"></div>
                <div class="dw-skeleton mt-3 h-4 w-96 max-w-[90%]"></div>
            </div>
            <div
                v-if="variant === 'table' || variant === 'detail'"
                class="dw-skeleton hidden h-10 w-36 sm:block"
            ></div>
        </div>

        <template v-if="variant === 'reference-overview'">
            <div class="grid gap-5 md:grid-cols-3">
                <section v-for="card in 3" :key="card" class="dw-card p-6">
                    <div class="dw-skeleton h-12 w-12 rounded-md"></div>
                    <div class="mt-5 flex items-end justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="dw-skeleton h-5 w-28"></div>
                            <div class="dw-skeleton mt-3 h-3 w-full"></div>
                            <div class="dw-skeleton mt-2 h-3 w-4/5"></div>
                        </div>
                        <div class="dw-skeleton h-9 w-12"></div>
                    </div>
                    <div class="dw-skeleton mt-6 h-3 w-32"></div>
                </section>
            </div>
        </template>

        <template v-else-if="variant === 'reference'">
            <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                <aside class="dw-card self-start overflow-hidden">
                    <div
                        class="border-b border-gray-200 p-5 dark:border-gray-900"
                    >
                        <div class="dw-skeleton h-5 w-44"></div>
                        <div class="dw-skeleton mt-2 h-3 w-64 max-w-full"></div>
                    </div>
                    <div class="space-y-5 p-5">
                        <div v-for="field in 3" :key="field">
                            <div class="dw-skeleton h-3 w-24"></div>
                            <div
                                class="dw-skeleton mt-2"
                                :class="field === 3 ? 'h-28' : 'h-11'"
                            ></div>
                        </div>
                        <div class="dw-skeleton h-10"></div>
                    </div>
                </aside>
                <section class="dw-card overflow-hidden">
                    <div
                        class="flex items-center justify-between border-b border-gray-200 p-5 dark:border-gray-900"
                    >
                        <div>
                            <div class="dw-skeleton h-5 w-48"></div>
                            <div class="dw-skeleton mt-2 h-3 w-24"></div>
                        </div>
                        <div class="dw-skeleton h-9 w-32"></div>
                    </div>
                    <div
                        class="border-b border-gray-200 p-4 dark:border-gray-900"
                    >
                        <div class="flex gap-3">
                            <div class="dw-skeleton h-11 flex-1"></div>
                            <div class="dw-skeleton h-11 w-28"></div>
                        </div>
                    </div>
                    <div
                        class="border-b border-gray-200 bg-gray-50 px-5 py-4 dark:border-gray-900 dark:bg-gray-1000"
                    >
                        <div
                            class="grid grid-cols-[1fr_100px_100px_100px] gap-5"
                        >
                            <div
                                v-for="item in 4"
                                :key="item"
                                class="dw-skeleton h-3"
                            ></div>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-gray-900">
                        <div
                            v-for="row in 7"
                            :key="row"
                            class="grid grid-cols-[1fr_100px_100px_100px] items-center gap-5 px-5 py-4"
                        >
                            <div>
                                <div
                                    class="dw-skeleton h-4"
                                    :class="row % 2 ? 'w-2/3' : 'w-1/2'"
                                ></div>
                                <div class="dw-skeleton mt-2 h-3 w-2/5"></div>
                            </div>
                            <div class="dw-skeleton h-5 w-16"></div>
                            <div
                                class="dw-skeleton h-6 w-10 rounded-full"
                            ></div>
                            <div class="flex gap-2">
                                <div class="dw-skeleton h-8 w-8"></div>
                                <div class="dw-skeleton h-8 w-8"></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </template>

        <template v-else-if="variant === 'form'">
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                <div class="space-y-6">
                    <section
                        v-for="section in 2"
                        :key="section"
                        class="dw-card overflow-hidden"
                    >
                        <div
                            class="flex items-center gap-4 border-b border-gray-200 p-5 dark:border-gray-900"
                        >
                            <div class="dw-skeleton h-11 w-11 rounded-lg"></div>
                            <div class="flex-1">
                                <div class="dw-skeleton h-4 w-44"></div>
                                <div
                                    class="dw-skeleton mt-2 h-3 w-64 max-w-full"
                                ></div>
                            </div>
                        </div>
                        <div class="grid gap-5 p-5 sm:grid-cols-2">
                            <div v-for="field in 4" :key="field">
                                <div class="dw-skeleton h-3 w-24"></div>
                                <div class="dw-skeleton mt-2 h-11 w-full"></div>
                            </div>
                            <div
                                v-if="section === 1"
                                class="dw-skeleton h-28 sm:col-span-2"
                            ></div>
                        </div>
                    </section>
                </div>
                <aside class="space-y-6">
                    <section class="dw-card p-5">
                        <div class="dw-skeleton h-4 w-36"></div>
                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div
                                v-for="item in 4"
                                :key="item"
                                class="dw-skeleton h-12"
                            ></div>
                        </div>
                        <div class="dw-skeleton mt-6 h-4 w-28"></div>
                        <div class="dw-skeleton mt-3 h-28"></div>
                    </section>
                    <div class="dw-card space-y-3 p-4">
                        <div class="dw-skeleton h-11"></div>
                        <div class="dw-skeleton h-10"></div>
                    </div>
                </aside>
            </div>
        </template>

        <template v-else-if="variant === 'dashboard'">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="item in 4"
                    :key="item"
                    class="dw-card flex items-center gap-4 p-5"
                >
                    <div class="dw-skeleton h-12 w-12 rounded-lg"></div>
                    <div class="flex-1">
                        <div class="dw-skeleton h-7 w-16"></div>
                        <div class="dw-skeleton mt-2 h-3 w-28"></div>
                    </div>
                </div>
            </div>
            <div class="mt-6 grid gap-6 xl:grid-cols-[1.5fr_1fr]">
                <div class="dw-card p-5">
                    <div class="dw-skeleton h-5 w-48"></div>
                    <div class="mt-8 flex h-64 items-end gap-3">
                        <div
                            v-for="(height, index) in [
                                45, 70, 38, 85, 62, 74, 52, 90,
                            ]"
                            :key="index"
                            class="dw-skeleton flex-1"
                            :style="{ height: `${height}%` }"
                        ></div>
                    </div>
                </div>
                <div class="dw-card p-5">
                    <div class="dw-skeleton h-5 w-40"></div>
                    <div
                        v-for="item in 5"
                        :key="item"
                        class="mt-5 flex items-center gap-3"
                    >
                        <div class="dw-skeleton h-10 w-10 rounded-full"></div>
                        <div class="flex-1">
                            <div class="dw-skeleton h-4 w-2/3"></div>
                            <div class="dw-skeleton mt-2 h-3 w-1/2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template v-else-if="variant === 'desk'">
            <div class="dw-card p-5">
                <div class="flex gap-3">
                    <div class="dw-skeleton h-12 flex-1"></div>
                    <div class="dw-skeleton hidden h-12 w-40 sm:block"></div>
                    <div class="dw-skeleton h-12 w-28"></div>
                </div>
            </div>
            <div class="mt-6 grid gap-6 xl:grid-cols-[360px_1fr]">
                <div class="dw-card overflow-hidden">
                    <div class="dw-skeleton h-64 rounded-none"></div>
                    <div class="space-y-5 p-5">
                        <div
                            v-for="item in 6"
                            :key="item"
                            class="flex justify-between"
                        >
                            <div class="dw-skeleton h-3 w-24"></div>
                            <div class="dw-skeleton h-3 w-20"></div>
                        </div>
                    </div>
                </div>
                <div class="space-y-6">
                    <div class="dw-card p-6">
                        <div class="dw-skeleton h-4 w-36"></div>
                        <div class="dw-skeleton mt-3 h-6 w-64"></div>
                        <div class="dw-skeleton mt-3 h-4 w-4/5"></div>
                        <div class="dw-skeleton mt-6 h-11 w-44"></div>
                    </div>
                    <div class="dw-card p-6">
                        <div class="dw-skeleton h-5 w-52"></div>
                        <div class="dw-skeleton mt-5 h-28"></div>
                    </div>
                </div>
            </div>
        </template>

        <template v-else-if="variant === 'detail'">
            <div class="grid gap-6 xl:grid-cols-[280px_1fr]">
                <aside class="dw-card p-5">
                    <div class="dw-skeleton mx-auto h-64 w-44"></div>
                    <div class="dw-skeleton mt-5 h-4 w-32"></div>
                    <div class="dw-skeleton mt-3 h-4 w-24"></div>
                    <div class="dw-skeleton mt-6 h-px"></div>
                    <div
                        v-for="item in 4"
                        :key="item"
                        class="dw-skeleton mt-5 h-3"
                    ></div>
                </aside>
                <div class="space-y-6">
                    <section
                        v-for="section in 3"
                        :key="section"
                        class="dw-card p-5"
                    >
                        <div class="dw-skeleton h-5 w-48"></div>
                        <div class="mt-6 grid gap-6 sm:grid-cols-2">
                            <div v-for="item in 4" :key="item">
                                <div class="dw-skeleton h-3 w-24"></div>
                                <div class="dw-skeleton mt-2 h-4 w-3/4"></div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="dw-card p-5">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="dw-skeleton h-11 flex-1"></div>
                    <div class="dw-skeleton h-11 w-full sm:w-48"></div>
                    <div class="dw-skeleton h-11 w-full sm:w-32"></div>
                </div>
            </div>
            <div class="dw-card mt-6 overflow-hidden">
                <div
                    class="border-b border-gray-200 bg-gray-50 px-5 py-4 dark:border-gray-900 dark:bg-gray-1000"
                >
                    <div class="dw-skeleton h-4 w-2/3 max-w-md"></div>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-900">
                    <div
                        v-for="row in 7"
                        :key="row"
                        class="flex items-center gap-4 px-5 py-4"
                    >
                        <div class="dw-skeleton h-4 w-4"></div>
                        <div class="dw-skeleton h-12 w-10"></div>
                        <div class="min-w-0 flex-1">
                            <div
                                class="dw-skeleton h-4"
                                :class="row % 2 ? 'w-2/5' : 'w-1/3'"
                            ></div>
                            <div class="dw-skeleton mt-2 h-3 w-1/4"></div>
                        </div>
                        <div class="dw-skeleton hidden h-4 w-28 md:block"></div>
                        <div class="dw-skeleton h-6 w-20 rounded-full"></div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
