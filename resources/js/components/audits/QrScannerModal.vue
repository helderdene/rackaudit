<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { Camera, XCircle, AlertTriangle, QrCode, CameraOff } from 'lucide-vue-next';

interface Props {
    open: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'scan', deviceId: number): void;
}>();

// State
const videoRef = ref<HTMLVideoElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);
const isScanning = ref(false);
const lastScannedUrl = ref<string | null>(null);

// Media stream reference for cleanup
let mediaStream: MediaStream | null = null;
let scanInterval: ReturnType<typeof setInterval> | null = null;

/**
 * Trigger haptic feedback on successful scan
 */
function triggerHapticFeedback(): void {
    // Use Navigator Vibration API if available
    if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
        navigator.vibrate(100);
    }
}

/**
 * Start the camera and begin scanning
 */
async function startCamera(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
        // Request camera access
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'environment', // Prefer back camera on mobile
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
        });

        // Attach stream to video element
        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
            await videoRef.value.play();
            isScanning.value = true;

            // Start scanning loop
            startScanning();
        }
    } catch (err) {
        console.error('Camera access error:', err);
        if (err instanceof DOMException) {
            if (err.name === 'NotAllowedError') {
                error.value = 'Camera access was denied. Please allow camera access to scan QR codes.';
            } else if (err.name === 'NotFoundError') {
                error.value = 'No camera found on this device.';
            } else if (err.name === 'NotReadableError') {
                error.value = 'Camera is in use by another application.';
            } else {
                error.value = `Camera error: ${err.message}`;
            }
        } else {
            error.value = 'Failed to access camera. Please check your device settings.';
        }
    } finally {
        isLoading.value = false;
    }
}

/**
 * Stop the camera and cleanup
 */
function stopCamera(): void {
    if (scanInterval) {
        clearInterval(scanInterval);
        scanInterval = null;
    }

    if (mediaStream) {
        mediaStream.getTracks().forEach(track => track.stop());
        mediaStream = null;
    }

    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }

    isScanning.value = false;
}

/**
 * Start scanning for QR codes
 */
function startScanning(): void {
    if (!videoRef.value || !canvasRef.value) return;

    const canvas = canvasRef.value;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    scanInterval = setInterval(() => {
        if (!videoRef.value || !isScanning.value) return;

        const video = videoRef.value;

        // Set canvas size to match video
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        // Draw current video frame to canvas
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Get image data for QR detection
        try {
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            detectQrCode(imageData);
        } catch (err) {
            // Canvas might not be ready yet
        }
    }, 250); // Scan 4 times per second
}

/**
 * Detect QR code in image data using BarcodeDetector API if available
 */
async function detectQrCode(imageData: ImageData): Promise<void> {
    // Check if BarcodeDetector is available (Chrome, Edge, Safari)
    if ('BarcodeDetector' in window) {
        try {
            const barcodeDetector = new (window as any).BarcodeDetector({
                formats: ['qr_code'],
            });

            const barcodes = await barcodeDetector.detect(imageData);

            if (barcodes.length > 0) {
                const qrData = barcodes[0].rawValue;
                handleScannedData(qrData);
            }
        } catch (err) {
            // Detection failed, continue scanning
        }
    } else {
        // Fallback: Try using a simple pattern match for our URL format
        // This is a limited fallback - in production you'd use a JS QR library
        // For now, we'll show a message that BarcodeDetector is required
        if (!error.value) {
            error.value = 'QR scanning requires a modern browser with BarcodeDetector support. Please use Chrome, Edge, or Safari.';
        }
    }
}

/**
 * Handle scanned QR code data
 */
function handleScannedData(data: string): void {
    // Prevent duplicate scans
    if (data === lastScannedUrl.value) return;
    lastScannedUrl.value = data;

    // Parse device ID from URL format: /devices/{id}
    const deviceMatch = data.match(/\/devices\/(\d+)/);

    if (deviceMatch) {
        const deviceId = parseInt(deviceMatch[1], 10);

        // Trigger haptic feedback on successful scan
        triggerHapticFeedback();

        // Stop scanning and close modal
        stopCamera();
        emit('update:open', false);
        emit('scan', deviceId);
    } else {
        // Not a valid device URL, reset and continue scanning
        setTimeout(() => {
            lastScannedUrl.value = null;
        }, 2000);
    }
}

/**
 * Handle manual device ID input (fallback)
 */
const manualDeviceId = ref<string>('');

function handleManualSubmit(): void {
    const deviceId = parseInt(manualDeviceId.value, 10);
    if (deviceId > 0) {
        // Trigger haptic feedback on manual submission too
        triggerHapticFeedback();

        stopCamera();
        emit('update:open', false);
        emit('scan', deviceId);
    }
}

// Watch for dialog open/close
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            // Reset state
            error.value = null;
            lastScannedUrl.value = null;
            manualDeviceId.value = '';

            // Start camera when dialog opens
            setTimeout(() => startCamera(), 100);
        } else {
            // Stop camera when dialog closes
            stopCamera();
        }
    }
);

// Cleanup on unmount
onUnmounted(() => {
    stopCamera();
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md sm:max-w-lg md:max-w-xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <QrCode class="size-5" />
                    Scan Device QR Code
                </DialogTitle>
                <DialogDescription>
                    Point your camera at a device QR code to quickly navigate to that device.
                </DialogDescription>
            </DialogHeader>

            <div class="py-4">
                <!-- Loading State -->
                <div v-if="isLoading" class="flex flex-col items-center justify-center py-12">
                    <Spinner class="size-8" />
                    <p class="mt-4 text-sm text-muted-foreground">Accessing camera...</p>
                </div>

                <!-- Error State -->
                <div
                    v-else-if="error"
                    class="flex flex-col items-center justify-center py-8"
                >
                    <CameraOff class="mb-4 size-12 text-muted-foreground" />
                    <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-400">
                        <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                        <p>{{ error }}</p>
                    </div>

                    <Button variant="outline" class="mt-4 min-h-11" @click="startCamera">
                        <Camera class="mr-1 size-4" />
                        Try Again
                    </Button>
                </div>

                <!-- Camera View -->
                <div v-else class="relative">
                    <div class="overflow-hidden rounded-lg bg-black">
                        <video
                            ref="videoRef"
                            class="h-auto w-full"
                            autoplay
                            playsinline
                            muted
                        />
                    </div>

                    <!-- Scanning indicator - Larger on tablet -->
                    <div
                        v-if="isScanning"
                        class="absolute inset-0 flex items-center justify-center pointer-events-none"
                    >
                        <div class="size-48 md:size-64 border-2 border-primary rounded-lg opacity-50" />
                    </div>

                    <!-- Scanning status -->
                    <div v-if="isScanning" class="mt-3 flex items-center justify-center gap-2 text-sm text-muted-foreground">
                        <Spinner class="size-4" />
                        Scanning for QR code...
                    </div>
                </div>

                <!-- Hidden canvas for QR processing -->
                <canvas ref="canvasRef" class="hidden" />

                <!-- Manual fallback - Enlarged for touch typing -->
                <div class="mt-6 border-t pt-4">
                    <p class="mb-2 text-sm text-muted-foreground">Or enter device ID manually:</p>
                    <div class="flex gap-2">
                        <input
                            v-model="manualDeviceId"
                            type="number"
                            min="1"
                            placeholder="Device ID"
                            class="flex h-11 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base md:text-lg shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            @keyup.enter="handleManualSubmit"
                        />
                        <Button
                            class="min-h-11 min-w-16"
                            :disabled="!manualDeviceId || parseInt(manualDeviceId) <= 0"
                            @click="handleManualSubmit"
                        >
                            Go
                        </Button>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="secondary" class="min-h-11" @click="emit('update:open', false)">
                        Close
                    </Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
