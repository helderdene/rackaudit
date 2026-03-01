/**
 * Type tests for connection utilities and interfaces
 *
 * These tests verify that TypeScript types are correctly defined and
 * utility functions work as expected. Tests are validated through
 * TypeScript compilation and runtime assertions.
 *
 * Run with: npx tsx resources/js/types/__tests__/connections.test.ts
 */

import {
    CABLE_TYPE_OPTIONS,
    getCableTypesForPortType,
    getDefaultCableTypeForPortType,
    isValidCableTypeForPort,
    type ConnectionData,
    type HierarchicalFilterOption,
} from '../connections';

// Simple assertion helper
function assert(condition: boolean, message: string): void {
    if (!condition) {
        throw new Error(`Assertion failed: ${message}`);
    }
}

function assertEqual<T>(actual: T, expected: T, message: string): void {
    if (actual !== expected) {
        throw new Error(
            `Assertion failed: ${message}. Expected ${expected}, got ${actual}`,
        );
    }
}

function assertArrayLength(
    arr: unknown[],
    expected: number,
    message: string,
): void {
    if (arr.length !== expected) {
        throw new Error(
            `Assertion failed: ${message}. Expected length ${expected}, got ${arr.length}`,
        );
    }
}

// Test 1: Cable type options filtering by port type - Ethernet
function testCableTypeOptionsForEthernet(): void {
    const ethernetCables = getCableTypesForPortType('ethernet');

    assertArrayLength(ethernetCables, 3, 'Ethernet should have 3 cable types');

    const values = ethernetCables.map((c) => c.value);
    assert(values.includes('cat5e'), 'Ethernet cables should include cat5e');
    assert(values.includes('cat6'), 'Ethernet cables should include cat6');
    assert(values.includes('cat6a'), 'Ethernet cables should include cat6a');
    assert(
        !values.includes('fiber_sm'),
        'Ethernet cables should not include fiber_sm',
    );
    assert(
        !values.includes('power_c13'),
        'Ethernet cables should not include power_c13',
    );

    console.log('PASS: testCableTypeOptionsForEthernet');
}

// Test 2: Cable type options filtering by port type - Fiber
function testCableTypeOptionsForFiber(): void {
    const fiberCables = getCableTypesForPortType('fiber');

    assertArrayLength(fiberCables, 2, 'Fiber should have 2 cable types');

    const values = fiberCables.map((c) => c.value);
    assert(values.includes('fiber_sm'), 'Fiber cables should include fiber_sm');
    assert(values.includes('fiber_mm'), 'Fiber cables should include fiber_mm');
    assert(!values.includes('cat5e'), 'Fiber cables should not include cat5e');
    assert(
        !values.includes('power_c13'),
        'Fiber cables should not include power_c13',
    );

    console.log('PASS: testCableTypeOptionsForFiber');
}

// Test 3: Cable type options filtering by port type - Power
function testCableTypeOptionsForPower(): void {
    const powerCables = getCableTypesForPortType('power');

    assertArrayLength(powerCables, 4, 'Power should have 4 cable types');

    const values = powerCables.map((c) => c.value);
    assert(
        values.includes('power_c13'),
        'Power cables should include power_c13',
    );
    assert(
        values.includes('power_c14'),
        'Power cables should include power_c14',
    );
    assert(
        values.includes('power_c19'),
        'Power cables should include power_c19',
    );
    assert(
        values.includes('power_c20'),
        'Power cables should include power_c20',
    );
    assert(!values.includes('cat5e'), 'Power cables should not include cat5e');
    assert(
        !values.includes('fiber_sm'),
        'Power cables should not include fiber_sm',
    );

    console.log('PASS: testCableTypeOptionsForPower');
}

// Test 4: Cable type validation utility
function testCableTypeValidation(): void {
    // Valid combinations
    assert(
        isValidCableTypeForPort('cat5e', 'ethernet'),
        'cat5e should be valid for ethernet',
    );
    assert(
        isValidCableTypeForPort('cat6', 'ethernet'),
        'cat6 should be valid for ethernet',
    );
    assert(
        isValidCableTypeForPort('fiber_sm', 'fiber'),
        'fiber_sm should be valid for fiber',
    );
    assert(
        isValidCableTypeForPort('power_c13', 'power'),
        'power_c13 should be valid for power',
    );

    // Invalid combinations
    assert(
        !isValidCableTypeForPort('cat5e', 'fiber'),
        'cat5e should not be valid for fiber',
    );
    assert(
        !isValidCableTypeForPort('fiber_sm', 'ethernet'),
        'fiber_sm should not be valid for ethernet',
    );
    assert(
        !isValidCableTypeForPort('power_c13', 'ethernet'),
        'power_c13 should not be valid for ethernet',
    );

    console.log('PASS: testCableTypeValidation');
}

// Test 5: Default cable type for port type
function testDefaultCableType(): void {
    const ethernetDefault = getDefaultCableTypeForPortType('ethernet');
    assert(
        ethernetDefault !== undefined,
        'Ethernet should have a default cable type',
    );
    assertEqual(
        ethernetDefault!.value,
        'cat5e',
        'Default ethernet cable should be cat5e',
    );

    const fiberDefault = getDefaultCableTypeForPortType('fiber');
    assert(
        fiberDefault !== undefined,
        'Fiber should have a default cable type',
    );
    assertEqual(
        fiberDefault!.value,
        'fiber_sm',
        'Default fiber cable should be fiber_sm',
    );

    const powerDefault = getDefaultCableTypeForPortType('power');
    assert(
        powerDefault !== undefined,
        'Power should have a default cable type',
    );
    assertEqual(
        powerDefault!.value,
        'power_c13',
        'Default power cable should be power_c13',
    );

    console.log('PASS: testDefaultCableType');
}

// Test 6: Type structure validation - ConnectionData
function testConnectionDataTypeStructure(): void {
    // This test validates that ConnectionData interface is correctly structured
    const validConnectionData: ConnectionData = {
        id: 1,
        cable_type: 'cat6',
        cable_type_label: 'Cat6',
        cable_length: 3.5,
        cable_color: 'blue',
        path_notes: 'Test notes',
        logical_path: [
            { id: 1, label: 'eth0', device_id: 1, device_name: 'Server 1' },
            { id: 2, label: 'port1', device_id: 2, device_name: 'Switch 1' },
        ],
        created_at: '2025-01-01T00:00:00.000000Z',
        updated_at: '2025-01-01T00:00:00.000000Z',
    };

    assert(typeof validConnectionData.id === 'number', 'id should be a number');
    assert(
        validConnectionData.cable_type === 'cat6',
        'cable_type should be cat6',
    );
    assert(
        Array.isArray(validConnectionData.logical_path),
        'logical_path should be an array',
    );

    console.log('PASS: testConnectionDataTypeStructure');
}

// Test 7: Type structure validation - HierarchicalFilterOption
function testHierarchicalFilterOptionStructure(): void {
    // Test basic hierarchical filter option (datacenter level)
    const datacenterOption: HierarchicalFilterOption = {
        value: 1,
        label: 'DC1',
    };

    // Test with room reference (room level)
    const roomOption: HierarchicalFilterOption = {
        value: 2,
        label: 'Room A',
        datacenter_id: 1,
    };

    // Test with row reference (row level)
    const rowOption: HierarchicalFilterOption = {
        value: 3,
        label: 'Row 1',
        room_id: 2,
    };

    // Test with rack reference (rack level)
    const rackOption: HierarchicalFilterOption = {
        value: 4,
        label: 'Rack A1',
        row_id: 3,
    };

    // Test with device reference (device level)
    const deviceOption: HierarchicalFilterOption = {
        value: 5,
        label: 'Server 1',
        rack_id: 4,
    };

    // Test with port reference (port level)
    const portOption: HierarchicalFilterOption = {
        value: 6,
        label: 'eth0',
        device_id: 5,
    };

    assert(
        typeof datacenterOption.value === 'number',
        'Datacenter value should be a number',
    );
    assert(roomOption.datacenter_id === 1, 'Room should have datacenter_id');
    assert(rowOption.room_id === 2, 'Row should have room_id');
    assert(rackOption.row_id === 3, 'Rack should have row_id');
    assert(deviceOption.rack_id === 4, 'Device should have rack_id');
    assert(portOption.device_id === 5, 'Port should have device_id');

    console.log('PASS: testHierarchicalFilterOptionStructure');
}

// Test 8: All cable types have correct structure
function testCableTypeOptionStructure(): void {
    assertArrayLength(
        CABLE_TYPE_OPTIONS,
        9,
        'Should have 9 total cable type options',
    );

    for (const option of CABLE_TYPE_OPTIONS) {
        assert(
            typeof option.value === 'string',
            `Cable type value should be a string: ${option.value}`,
        );
        assert(
            typeof option.label === 'string',
            `Cable type label should be a string: ${option.label}`,
        );
        assert(
            Array.isArray(option.port_types),
            `Cable type port_types should be an array: ${option.value}`,
        );
        assert(
            option.port_types.length > 0,
            `Cable type should have at least one port_type: ${option.value}`,
        );
    }

    console.log('PASS: testCableTypeOptionStructure');
}

// Run all tests
function runTests(): void {
    console.log('Running connection type utility tests...\n');

    try {
        testCableTypeOptionsForEthernet();
        testCableTypeOptionsForFiber();
        testCableTypeOptionsForPower();
        testCableTypeValidation();
        testDefaultCableType();
        testConnectionDataTypeStructure();
        testHierarchicalFilterOptionStructure();
        testCableTypeOptionStructure();

        console.log('\nAll tests passed!');
    } catch (error) {
        console.error('\nTest failed:', error);
        process.exit(1);
    }
}

// Run tests when executed directly
runTests();
