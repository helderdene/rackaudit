<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import QRCode from 'qrcode';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Download, Printer, QrCode } from 'lucide-vue-next';

interface Props {
    entityType: 'rack' | 'device';
    entityId: number;
    entityName: string;
    secondaryLabel?: string | null;
    /**
     * Optional full URL path for the entity.
     * Required for racks which have nested URLs.
     * For devices, this can be omitted and will default to /devices/{id}
     */
    entityPath?: string;
}

const props = defineProps<Props>();

const isOpen = ref(false);
const qrCodeDataUrl = ref<string>('');
const qrCodeSvg = ref<string>('');
const isGenerating = ref(false);

// Generate the full URL for the entity
const entityUrl = computed(() => {
    const baseUrl = window.location.origin;
    if (props.entityPath) {
        // Use the provided path (for racks with nested URLs)
        return `${baseUrl}${props.entityPath}`;
    }
    // Default to device URL format
    return `${baseUrl}/devices/${props.entityId}`;
});

// Generate QR codes when dialog opens
watch(isOpen, async (newValue) => {
    if (newValue) {
        await generateQrCodes();
    }
});

const generateQrCodes = async () => {
    isGenerating.value = true;
    try {
        // Generate PNG data URL
        qrCodeDataUrl.value = await QRCode.toDataURL(entityUrl.value, {
            width: 200,
            margin: 2,
            color: {
                dark: '#000000',
                light: '#ffffff',
            },
            errorCorrectionLevel: 'M',
        });

        // Generate SVG string
        qrCodeSvg.value = await QRCode.toString(entityUrl.value, {
            type: 'svg',
            width: 200,
            margin: 2,
            color: {
                dark: '#000000',
                light: '#ffffff',
            },
            errorCorrectionLevel: 'M',
        });
    } catch (error) {
        console.error('Failed to generate QR code:', error);
    } finally {
        isGenerating.value = false;
    }
};

// Create a canvas with QR code and label text for PNG download
const createLabelCanvas = async (width: number, height: number): Promise<HTMLCanvasElement> => {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d')!;

    // Set canvas size (2" x 2" at 150 DPI = 300x300, 2" x 1" = 300x150)
    canvas.width = width;
    canvas.height = height;

    // Fill white background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);

    // Generate QR code to canvas
    const qrSize = Math.min(width - 40, height - 80);
    const qrX = (width - qrSize) / 2;
    const qrY = 20;

    // Create temporary canvas for QR code
    const qrCanvas = document.createElement('canvas');
    await QRCode.toCanvas(qrCanvas, entityUrl.value, {
        width: qrSize,
        margin: 0,
        color: {
            dark: '#000000',
            light: '#ffffff',
        },
        errorCorrectionLevel: 'M',
    });

    // Draw QR code onto main canvas
    ctx.drawImage(qrCanvas, qrX, qrY, qrSize, qrSize);

    // Draw entity name
    ctx.fillStyle = '#000000';
    ctx.textAlign = 'center';
    ctx.font = 'bold 14px Arial';
    const nameY = qrY + qrSize + 20;
    ctx.fillText(truncateText(props.entityName, 30), width / 2, nameY);

    // Draw secondary label if present
    if (props.secondaryLabel) {
        ctx.font = '12px Arial';
        ctx.fillText(truncateText(props.secondaryLabel, 35), width / 2, nameY + 18);
    }

    return canvas;
};

// Truncate text to fit within label
const truncateText = (text: string, maxLength: number): string => {
    if (text.length <= maxLength) {
        return text;
    }
    return text.substring(0, maxLength - 3) + '...';
};

// Download PNG
const downloadPng = async () => {
    const canvas = await createLabelCanvas(300, 300);
    const dataUrl = canvas.toDataURL('image/png');

    const link = document.createElement('a');
    link.download = `${props.entityType}-${props.entityId}-qr.png`;
    link.href = dataUrl;
    link.click();
};

// Download SVG
const downloadSvg = async () => {
    // Create SVG with label
    const svgContent = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300" viewBox="0 0 300 300">
    <rect width="300" height="300" fill="white"/>
    <g transform="translate(50, 20)">
        ${qrCodeSvg.value.replace(/<\?xml[^>]*\?>/, '').replace(/<svg[^>]*>/, '').replace(/<\/svg>/, '')}
    </g>
    <text x="150" y="260" text-anchor="middle" font-family="Arial" font-size="14" font-weight="bold" fill="black">${escapeXml(truncateText(props.entityName, 30))}</text>
    ${props.secondaryLabel ? `<text x="150" y="280" text-anchor="middle" font-family="Arial" font-size="12" fill="black">${escapeXml(truncateText(props.secondaryLabel, 35))}</text>` : ''}
</svg>`;

    const blob = new Blob([svgContent], { type: 'image/svg+xml' });
    const url = URL.createObjectURL(blob);

    const link = document.createElement('a');
    link.download = `${props.entityType}-${props.entityId}-qr.svg`;
    link.href = url;
    link.click();

    URL.revokeObjectURL(url);
};

// Escape XML special characters
const escapeXml = (text: string): string => {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
};

// Print QR code label
const printLabel = () => {
    const printContent = `
<!DOCTYPE html>
<html>
<head>
    <title>QR Code Label - ${escapeXml(props.entityName)}</title>
    <style>
        @page {
            size: 2in 2in;
            margin: 0;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 10px;
            box-sizing: border-box;
        }
        .qr-container {
            text-align: center;
        }
        .qr-code {
            width: 150px;
            height: 150px;
        }
        .entity-name {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
            word-break: break-word;
        }
        .secondary-label {
            font-size: 12px;
            color: #333;
            margin-top: 4px;
            word-break: break-word;
        }
    </style>
</head>
<body>
    <div class="qr-container">
        <img src="${qrCodeDataUrl.value}" alt="QR Code" class="qr-code" />
        <div class="entity-name">${escapeXml(props.entityName)}</div>
        ${props.secondaryLabel ? `<div class="secondary-label">${escapeXml(props.secondaryLabel)}</div>` : ''}
    </div>
</body>
</html>`;

    const printWindow = window.open('', '_blank');
    if (printWindow) {
        printWindow.document.write(printContent);
        printWindow.document.close();
        printWindow.focus();
        // Small delay to ensure content is loaded
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }
};

// Format entity type for display
const entityTypeLabel = computed(() => {
    return props.entityType === 'device' ? 'Device' : 'Rack';
});
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <slot>
                <Button variant="outline" size="sm">
                    <QrCode class="size-4" />
                    QR Code
                </Button>
            </slot>
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="space-y-3">
                <DialogTitle>{{ entityTypeLabel }} QR Code</DialogTitle>
                <DialogDescription>
                    Scan this QR code to access the {{ entityTypeLabel.toLowerCase() }} details page.
                </DialogDescription>
            </DialogHeader>

            <!-- QR Code Preview -->
            <div class="flex flex-col items-center gap-4 py-4">
                <div
                    v-if="isGenerating"
                    class="flex size-[200px] items-center justify-center rounded-lg border bg-muted"
                >
                    <div class="animate-pulse text-muted-foreground">
                        Generating...
                    </div>
                </div>
                <div
                    v-else-if="qrCodeDataUrl"
                    class="rounded-lg border bg-white p-2"
                >
                    <img
                        :src="qrCodeDataUrl"
                        :alt="`QR Code for ${entityName}`"
                        class="size-[200px]"
                    />
                </div>

                <!-- Label Info -->
                <div class="text-center">
                    <p class="text-lg font-semibold">{{ entityName }}</p>
                    <p
                        v-if="secondaryLabel"
                        class="font-mono text-sm text-muted-foreground"
                    >
                        {{ secondaryLabel }}
                    </p>
                </div>

                <!-- URL Preview -->
                <div class="w-full rounded-md bg-muted p-2">
                    <p class="break-all text-center font-mono text-xs text-muted-foreground">
                        {{ entityUrl }}
                    </p>
                </div>
            </div>

            <DialogFooter class="flex-col gap-2 sm:flex-row">
                <div class="flex flex-1 gap-2">
                    <Button
                        variant="outline"
                        class="flex-1"
                        :disabled="isGenerating"
                        @click="downloadPng"
                    >
                        <Download class="size-4" />
                        PNG
                    </Button>
                    <Button
                        variant="outline"
                        class="flex-1"
                        :disabled="isGenerating"
                        @click="downloadSvg"
                    >
                        <Download class="size-4" />
                        SVG
                    </Button>
                    <Button
                        variant="outline"
                        class="flex-1"
                        :disabled="isGenerating"
                        @click="printLabel"
                    >
                        <Printer class="size-4" />
                        Print
                    </Button>
                </div>
                <DialogClose as-child>
                    <Button variant="secondary">
                        Close
                    </Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
