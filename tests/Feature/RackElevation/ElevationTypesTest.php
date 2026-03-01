<?php

/**
 * Tests for TypeScript type definitions used by the Rack Elevation feature
 * These tests verify that the TypeScript interfaces are properly defined and exported
 */

use Illuminate\Support\Facades\File;

/**
 * Test 1: Verify rooms.ts TypeScript file exists and contains PlaceholderDevice interface
 */
test('PlaceholderDevice interface is defined in rooms.ts', function () {
    $typesFile = resource_path('js/types/rooms.ts');

    expect(File::exists($typesFile))->toBeTrue();

    $content = File::get($typesFile);

    // Verify PlaceholderDevice interface exists with required fields
    expect($content)->toContain('export interface PlaceholderDevice');
    expect($content)->toContain('id: string');
    expect($content)->toContain('name: string');
    expect($content)->toContain('type: string');
    expect($content)->toContain('u_size: number');
    expect($content)->toContain('width: DeviceWidth');
    expect($content)->toContain('start_u?: number');
    expect($content)->toContain('face?: RackFace');
});

/**
 * Test 2: Verify DevicePosition interface is defined with required fields
 */
test('DevicePosition interface is defined with correct fields', function () {
    $typesFile = resource_path('js/types/rooms.ts');
    $content = File::get($typesFile);

    // Verify DevicePosition interface exists with required fields
    expect($content)->toContain('export interface DevicePosition');
    expect($content)->toContain('device_id: string');
    expect($content)->toContain('start_u: number');
    expect($content)->toContain('face: RackFace');
    expect($content)->toContain('width: DeviceWidth');
});

/**
 * Test 3: Verify RackElevationState and UtilizationStats interfaces are defined
 */
test('RackElevationState and UtilizationStats interfaces are defined', function () {
    $typesFile = resource_path('js/types/rooms.ts');
    $content = File::get($typesFile);

    // Verify RackElevationState interface
    expect($content)->toContain('export interface RackElevationState');
    expect($content)->toContain('placedDevices: PlaceholderDevice[]');
    expect($content)->toContain('unplacedDevices: PlaceholderDevice[]');
    expect($content)->toContain('draggedDevice: PlaceholderDevice | null');

    // Verify UtilizationStats interface
    expect($content)->toContain('export interface UtilizationStats');
    expect($content)->toContain('totalU: number');
    expect($content)->toContain('usedU: number');
    expect($content)->toContain('availableU: number');
    expect($content)->toContain('utilizationPercent: number');
    expect($content)->toContain('frontUsedU?: number');
    expect($content)->toContain('rearUsedU?: number');
});

/**
 * Test 4: Verify DeviceWidth and RackFace type aliases are defined
 */
test('DeviceWidth and RackFace type aliases are properly defined', function () {
    $typesFile = resource_path('js/types/rooms.ts');
    $content = File::get($typesFile);

    // Verify DeviceWidth type with correct union values
    expect($content)->toContain("export type DeviceWidth = 'full' | 'half-left' | 'half-right'");

    // Verify RackFace type with correct union values
    expect($content)->toContain("export type RackFace = 'front' | 'rear'");
});
