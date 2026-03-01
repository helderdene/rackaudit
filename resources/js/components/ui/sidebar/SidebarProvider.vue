<script setup lang="ts">
import type { HTMLAttributes, Ref } from "vue"
import { defaultDocument, useEventListener, useMediaQuery, useVModel } from "@vueuse/core"
import { TooltipProvider } from "reka-ui"
import { computed, ref, watch } from "vue"
import { cn } from "@/lib/utils"
import { provideSidebarContext, SIDEBAR_COOKIE_MAX_AGE, SIDEBAR_COOKIE_NAME, SIDEBAR_KEYBOARD_SHORTCUT, SIDEBAR_WIDTH, SIDEBAR_WIDTH_ICON } from "./utils"

const props = withDefaults(defineProps<{
  defaultOpen?: boolean
  open?: boolean
  class?: HTMLAttributes["class"]
}>(), {
  defaultOpen: !defaultDocument?.cookie.includes(`${SIDEBAR_COOKIE_NAME}=false`),
  open: undefined,
})

const emits = defineEmits<{
  "update:open": [open: boolean]
}>()

// Viewport detection for mobile and tablet
const isMobile = useMediaQuery("(max-width: 767px)")
const isTablet = useMediaQuery("(min-width: 768px) and (max-width: 1024px)")
const openMobile = ref(false)

// Track if user has manually toggled sidebar during this session
const userHasToggledManually = ref(false)

const open = useVModel(props, "open", emits, {
  defaultValue: props.defaultOpen ?? false,
  passive: (props.open === undefined) as false,
}) as Ref<boolean>

function setOpen(value: boolean, isUserAction = true) {
  open.value = value // emits('update:open', value)

  // Track user manual toggle
  if (isUserAction) {
    userHasToggledManually.value = true
  }

  // This sets the cookie to keep the sidebar state.
  document.cookie = `${SIDEBAR_COOKIE_NAME}=${open.value}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`
}

function setOpenMobile(value: boolean) {
  openMobile.value = value
}

// Helper to toggle the sidebar.
function toggleSidebar() {
  return isMobile.value ? setOpenMobile(!openMobile.value) : setOpen(!open.value, true)
}

// Auto-collapse sidebar on tablet viewport when user hasn't manually toggled
watch(isTablet, (newIsTablet, oldIsTablet) => {
  // Only auto-collapse when entering tablet viewport and user hasn't manually toggled
  if (newIsTablet && !oldIsTablet && !userHasToggledManually.value) {
    setOpen(false, false)
  }
  // When leaving tablet viewport (going to desktop), expand if not manually toggled
  if (!newIsTablet && oldIsTablet && !isMobile.value && !userHasToggledManually.value) {
    setOpen(true, false)
  }
}, { immediate: false })

// Reset manual toggle tracking when viewport changes significantly
watch(isMobile, (newIsMobile, oldIsMobile) => {
  // When transitioning from mobile to non-mobile, reset manual toggle tracking
  if (!newIsMobile && oldIsMobile) {
    userHasToggledManually.value = false
    // If entering tablet viewport, auto-collapse
    if (isTablet.value) {
      setOpen(false, false)
    }
  }
})

useEventListener("keydown", (event: KeyboardEvent) => {
  if (event.key === SIDEBAR_KEYBOARD_SHORTCUT && (event.metaKey || event.ctrlKey)) {
    event.preventDefault()
    toggleSidebar()
  }
})

// We add a state so that we can do data-state="expanded" or "collapsed".
// This makes it easier to style the sidebar with Tailwind classes.
const state = computed(() => open.value ? "expanded" : "collapsed")

provideSidebarContext({
  state,
  open,
  setOpen: (value: boolean) => setOpen(value, true),
  isMobile,
  openMobile,
  setOpenMobile,
  toggleSidebar,
})
</script>

<template>
  <TooltipProvider :delay-duration="0">
    <div
      data-slot="sidebar-wrapper"
      :style="{
        '--sidebar-width': SIDEBAR_WIDTH,
        '--sidebar-width-icon': SIDEBAR_WIDTH_ICON,
      }"
      :class="cn('group/sidebar-wrapper has-data-[variant=inset]:bg-sidebar flex min-h-svh w-full', props.class)"
      v-bind="$attrs"
    >
      <slot />
    </div>
  </TooltipProvider>
</template>
