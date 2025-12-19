<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Schema Visualizer</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Cytoscape.js Core -->
    <script src="https://unpkg.com/cytoscape@3.28.1/dist/cytoscape.min.js"></script>

    <!-- Dagre Layout Plugin -->
    <script src="https://unpkg.com/dagre@0.8.5/dist/dagre.min.js"></script>
    <script src="https://unpkg.com/cytoscape-dagre@2.5.0/cytoscape-dagre.js"></script>

    <!-- FileSaver for PNG Export -->
    <script src="https://unpkg.com/file-saver@2.0.5/dist/FileSaver.min.js"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'fk-blue': '#3B82F6',
                        'trigger-red': '#EF4444',
                        'node-bg': '#1E293B',
                        'node-border': '#475569',
                    }
                }
            }
        }
    </script>

    <style>
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #1E293B;
        }
        ::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #64748B;
        }

        /* Cytoscape container */
        #cy {
            width: 100%;
            height: 100%;
            background-color: #0F172A;
        }

        /* Loading spinner */
        .loader {
            border: 4px solid #1E293B;
            border-top: 4px solid #3B82F6;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Tooltip */
        .tooltip {
            position: absolute;
            background: #1E293B;
            border: 1px solid #475569;
            border-radius: 8px;
            padding: 12px;
            pointer-events: none;
            z-index: 1000;
            max-width: 300px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }

        /* Floating panels */
        .floating-panel {
            backdrop-filter: blur(8px);
            background: rgba(30, 41, 59, 0.95);
        }

        /* Isolated table item hover */
        .isolated-item {
            transition: all 0.15s ease;
        }
        .isolated-item:hover {
            background-color: rgba(59, 130, 246, 0.2);
            border-color: #3B82F6;
        }

        /* Selection info badge */
        .selection-badge {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 100;
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 h-screen overflow-hidden">
    <!-- Main Container -->
    <div class="flex flex-col h-full">

        <!-- Header -->
        <header class="bg-slate-800 border-b border-slate-700 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3">
                    <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                    </svg>
                    <h1 class="text-xl font-bold">Database Schema Visualizer</h1>
                </div>
                <span id="db-info" class="text-sm text-slate-400 hidden"></span>
            </div>

            <!-- Search Bar -->
            <div class="flex items-center gap-4">
                <div class="relative">
                    <input
                        type="text"
                        id="search-input"
                        placeholder="Search tables..."
                        class="w-72 bg-slate-700 border border-slate-600 rounded-lg px-4 py-2 pl-10 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                    />
                    <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <button id="clear-search" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200 hidden" title="Clear search">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2">
                    <button id="btn-reset-positions" class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors" title="Reset saved positions">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                    <button id="btn-fit" class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors" title="Fit to screen">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                        </svg>
                    </button>
                    <button id="btn-relayout" class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors" title="Re-layout graph (clears saved positions)">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                    <button id="btn-export" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded-lg transition-colors font-medium" title="Export as PNG">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        <span>Export PNG</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content Area with Sidebar -->
        <div class="flex-1 flex overflow-hidden">

            <!-- Left Sidebar - Isolated Tables -->
            <div id="isolated-sidebar" class="w-64 bg-slate-800 border-r border-slate-700 flex flex-col flex-shrink-0 hidden">
                <div class="p-4 border-b border-slate-700">
                    <div class="flex items-center gap-2 text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <h2 class="font-semibold text-sm uppercase tracking-wider">Isolated Tables</h2>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Tables with no relationships</p>
                </div>
                <div class="p-3 border-b border-slate-700">
                    <input type="text" id="isolated-search" placeholder="Filter isolated..." class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" />
                </div>
                <div id="isolated-list" class="flex-1 overflow-y-auto p-2 space-y-1"></div>
                <div class="p-3 border-t border-slate-700 text-xs text-slate-500">
                    <span id="isolated-count">0</span> isolated tables
                </div>
            </div>

            <!-- Graph Container -->
            <div class="flex-1 relative overflow-hidden">
                <div id="cy" class="absolute inset-0"></div>

                <!-- Loading Overlay -->
                <div id="loading-overlay" class="absolute inset-0 bg-slate-900 flex items-center justify-center z-50">
                    <div class="text-center">
                        <div class="loader mx-auto mb-4"></div>
                        <p class="text-slate-400">Loading schema data...</p>
                    </div>
                </div>

                <!-- Error Overlay -->
                <div id="error-overlay" class="absolute inset-0 bg-slate-900 flex items-center justify-center z-50 hidden">
                    <div class="text-center max-w-md p-8">
                        <svg class="w-16 h-16 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <h2 class="text-xl font-bold mb-2">Connection Error</h2>
                        <p id="error-message" class="text-slate-400 mb-4">Unable to load schema data.</p>
                        <button id="btn-retry" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded-lg transition-colors">Retry</button>
                    </div>
                </div>

                <!-- Legend Panel -->
                <div class="floating-panel absolute bottom-4 left-4 rounded-xl border border-slate-700 p-4 z-40">
                    <h3 class="font-semibold mb-3 text-sm uppercase tracking-wider text-slate-400">Legend</h3>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-0.5 bg-blue-500"></div>
                            <span class="text-sm">Foreign Key</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-0.5 border-t-2 border-dashed border-red-500"></div>
                            <span class="text-sm">Trigger Dependency</span>
                        </div>
                        <div class="flex items-center gap-3 mt-3 pt-3 border-t border-slate-700">
                            <div class="w-6 h-6 rounded bg-slate-700 border-2 border-blue-500"></div>
                            <span class="text-sm">Connected Table</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-6 h-6 rounded bg-yellow-900 border-2 border-yellow-500"></div>
                            <span class="text-sm">Selected (multi)</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-slate-700 text-xs text-slate-500">
                        <p>Shift+Click: Multi-select</p>
                        <p>Drag box: Select group</p>
                    </div>
                </div>

                <!-- Stats Panel -->
                <div class="floating-panel absolute bottom-4 right-4 rounded-xl border border-slate-700 p-4 z-40">
                    <h3 class="font-semibold mb-3 text-sm uppercase tracking-wider text-slate-400">Statistics</h3>
                    <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                        <div class="text-slate-400">Total Tables:</div>
                        <div id="stat-tables" class="font-mono text-right">-</div>
                        <div class="text-slate-400">Connected:</div>
                        <div id="stat-connected" class="font-mono text-right text-green-400">-</div>
                        <div class="text-slate-400">Foreign Keys:</div>
                        <div id="stat-fk" class="font-mono text-right text-blue-400">-</div>
                        <div class="text-slate-400">Trigger Deps:</div>
                        <div id="stat-triggers" class="font-mono text-right text-red-400">-</div>
                    </div>
                </div>

                <!-- Zoom Controls -->
                <div class="floating-panel absolute top-4 right-4 rounded-xl border border-slate-700 z-40 flex flex-col">
                    <button id="btn-zoom-in" class="p-3 hover:bg-slate-700 rounded-t-xl transition-colors" title="Zoom in">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </button>
                    <div class="border-t border-slate-700"></div>
                    <button id="btn-zoom-out" class="p-3 hover:bg-slate-700 rounded-b-xl transition-colors" title="Zoom out">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                        </svg>
                    </button>
                </div>

                <!-- Selection Badge -->
                <div id="selection-badge" class="selection-badge hidden">
                    <div class="bg-yellow-600 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-3">
                        <span id="selection-count">0</span> tables selected
                        <button id="btn-clear-selection" class="hover:bg-yellow-500 p-1 rounded">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Tooltip -->
                <div id="tooltip" class="tooltip hidden"></div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            'use strict';

            // ============================================================
            // Configuration & State
            // ============================================================

            const STORAGE_KEY = 'schema_visualizer_state';
            let cy = null;
            let schemaData = null;
            let isolatedTables = [];
            let isDragging = false;

            // DOM Elements
            const elements = {
                cy: document.getElementById('cy'),
                loadingOverlay: document.getElementById('loading-overlay'),
                errorOverlay: document.getElementById('error-overlay'),
                errorMessage: document.getElementById('error-message'),
                searchInput: document.getElementById('search-input'),
                clearSearch: document.getElementById('clear-search'),
                tooltip: document.getElementById('tooltip'),
                dbInfo: document.getElementById('db-info'),
                statTables: document.getElementById('stat-tables'),
                statConnected: document.getElementById('stat-connected'),
                statFk: document.getElementById('stat-fk'),
                statTriggers: document.getElementById('stat-triggers'),
                btnFit: document.getElementById('btn-fit'),
                btnRelayout: document.getElementById('btn-relayout'),
                btnExport: document.getElementById('btn-export'),
                btnRetry: document.getElementById('btn-retry'),
                btnZoomIn: document.getElementById('btn-zoom-in'),
                btnZoomOut: document.getElementById('btn-zoom-out'),
                btnResetPositions: document.getElementById('btn-reset-positions'),
                isolatedSidebar: document.getElementById('isolated-sidebar'),
                isolatedList: document.getElementById('isolated-list'),
                isolatedCount: document.getElementById('isolated-count'),
                isolatedSearch: document.getElementById('isolated-search'),
                selectionBadge: document.getElementById('selection-badge'),
                selectionCount: document.getElementById('selection-count'),
                btnClearSelection: document.getElementById('btn-clear-selection')
            };

            // ============================================================
            // Cytoscape Styles
            // ============================================================

            const cytoscapeStyles = [
                {
                    selector: 'node',
                    style: {
                        'label': 'data(label)',
                        'text-valign': 'center',
                        'text-halign': 'center',
                        'background-color': '#334155',
                        'border-width': 2,
                        'border-color': '#3B82F6',
                        'color': '#F1F5F9',
                        'font-size': '11px',
                        'font-weight': 500,
                        'text-wrap': 'wrap',
                        'text-max-width': '100px',
                        'width': 'label',
                        'height': 'label',
                        'padding': '12px',
                        'shape': 'roundrectangle',
                        'transition-property': 'background-color, border-color, opacity',
                        'transition-duration': '0.2s'
                    }
                },
                {
                    selector: 'node[connections >= 5]',
                    style: {
                        'background-color': '#1E3A5F',
                        'border-color': '#60A5FA',
                        'border-width': 3,
                        'font-weight': 600
                    }
                },
                {
                    selector: 'node:selected',
                    style: {
                        'background-color': '#78350F',
                        'border-color': '#F59E0B',
                        'border-width': 3,
                        'z-index': 1000
                    }
                },
                {
                    selector: 'node.highlighted',
                    style: {
                        'background-color': '#1D4ED8',
                        'border-color': '#93C5FD',
                        'border-width': 4,
                        'color': '#FFFFFF',
                        'z-index': 999
                    }
                },
                {
                    selector: 'node.neighbor',
                    style: {
                        'background-color': '#475569',
                        'border-color': '#94A3B8',
                        'border-width': 2,
                        'z-index': 998
                    }
                },
                {
                    selector: 'node.dimmed',
                    style: {
                        'opacity': 0.15
                    }
                },
                {
                    selector: 'node:active',
                    style: {
                        'overlay-color': '#3B82F6',
                        'overlay-padding': 8,
                        'overlay-opacity': 0.2
                    }
                },
                {
                    selector: 'edge',
                    style: {
                        'width': 2,
                        'curve-style': 'bezier',
                        'target-arrow-shape': 'triangle',
                        'target-arrow-color': '#64748B',
                        'line-color': '#64748B',
                        'arrow-scale': 1.2,
                        'transition-property': 'line-color, opacity',
                        'transition-duration': '0.2s'
                    }
                },
                {
                    selector: 'edge[type = "foreign_key"]',
                    style: {
                        'line-color': '#3B82F6',
                        'target-arrow-color': '#3B82F6',
                        'line-style': 'solid',
                        'width': 2
                    }
                },
                {
                    selector: 'edge[type = "trigger"]',
                    style: {
                        'line-color': '#EF4444',
                        'target-arrow-color': '#EF4444',
                        'line-style': 'dashed',
                        'width': 2
                    }
                },
                {
                    selector: 'edge.highlighted',
                    style: {
                        'width': 4,
                        'z-index': 999
                    }
                },
                {
                    selector: 'edge.dimmed',
                    style: {
                        'opacity': 0.08
                    }
                },
                {
                    selector: 'core',
                    style: {
                        'selection-box-color': '#F59E0B',
                        'selection-box-border-color': '#D97706',
                        'selection-box-border-width': 2,
                        'selection-box-opacity': 0.3
                    }
                }
            ];

            // ============================================================
            // LocalStorage Functions
            // ============================================================

            function saveState() {
                if (!cy) return;

                const positions = {};
                cy.nodes().forEach(node => {
                    const pos = node.position();
                    positions[node.id()] = { x: pos.x, y: pos.y };
                });

                const state = {
                    positions: positions,
                    pan: cy.pan(),
                    zoom: cy.zoom(),
                    timestamp: Date.now()
                };

                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
                } catch (e) {
                    console.warn('Failed to save state to localStorage:', e);
                }
            }

            function loadState() {
                try {
                    const saved = localStorage.getItem(STORAGE_KEY);
                    if (saved) {
                        return JSON.parse(saved);
                    }
                } catch (e) {
                    console.warn('Failed to load state from localStorage:', e);
                }
                return null;
            }

            function clearSavedState() {
                try {
                    localStorage.removeItem(STORAGE_KEY);
                } catch (e) {
                    console.warn('Failed to clear localStorage:', e);
                }
            }

            // ============================================================
            // Data Loading
            // ============================================================

            async function loadSchemaData() {
                try {
                    showLoading(true);
                    hideError();

                    const response = await fetch('get_schema.php');
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    const data = await response.json();
                    if (data.error) {
                        throw new Error(data.message || 'Unknown error occurred');
                    }

                    schemaData = data;
                    const { connectedNodes, isolatedNodes } = separateNodes(data);
                    isolatedTables = isolatedNodes;

                    initializeCytoscape(connectedNodes, data.edges);
                    populateIsolatedSidebar(isolatedNodes);
                    updateStats(data, connectedNodes.length, isolatedNodes.length);
                    updateDbInfo(data.meta);
                    showLoading(false);

                } catch (error) {
                    console.error('Failed to load schema data:', error);
                    showError(error.message);
                }
            }

            function separateNodes(data) {
                const connectedNodes = [];
                const isolatedNodes = [];

                data.nodes.forEach(node => {
                    if (node.data.isolated) {
                        isolatedNodes.push(node);
                    } else {
                        connectedNodes.push(node);
                    }
                });

                isolatedNodes.sort((a, b) => a.data.label.localeCompare(b.data.label));
                return { connectedNodes, isolatedNodes };
            }

            // ============================================================
            // Cytoscape Initialization
            // ============================================================

            function initializeCytoscape(nodes, edges) {
                if (cy) {
                    cy.destroy();
                }

                const savedState = loadState();
                const hasSavedPositions = savedState && savedState.positions && Object.keys(savedState.positions).length > 0;

                // Apply saved positions to nodes if available
                if (hasSavedPositions) {
                    nodes = nodes.map(node => {
                        const savedPos = savedState.positions[node.data.id];
                        if (savedPos) {
                            return {
                                ...node,
                                position: { x: savedPos.x, y: savedPos.y }
                            };
                        }
                        return node;
                    });
                }

                // Create Cytoscape instance
                cy = cytoscape({
                    container: elements.cy,
                    elements: [...nodes, ...edges],
                    style: cytoscapeStyles,
                    layout: hasSavedPositions ? { name: 'preset' } : getLayoutConfig(nodes.length, edges.length),
                    minZoom: 0.02,
                    maxZoom: 4,
                    wheelSensitivity: 0.3,
                    boxSelectionEnabled: true,
                    selectionType: 'additive',
                    autoungrabify: false
                });

                // Restore pan and zoom if saved
                if (hasSavedPositions && savedState.pan && savedState.zoom) {
                    cy.viewport({
                        pan: savedState.pan,
                        zoom: savedState.zoom
                    });
                } else {
                    // Fit to screen on first load
                    cy.fit(undefined, 50);
                }

                setupEventHandlers();
            }

            function getLayoutConfig(nodeCount, edgeCount) {
                // Calculate spacing based on complexity
                const complexity = edgeCount / Math.max(1, nodeCount);
                const repulsionMultiplier = Math.max(1, complexity * 2);
                const edgeLengthMultiplier = Math.max(1, complexity * 1.5);

                return {
                    name: 'cose',
                    animate: false,
                    padding: 120,
                    // Strong node repulsion to push nodes apart
                    nodeRepulsion: function(node) {
                        return 80000 * repulsionMultiplier;
                    },
                    // Longer edges for better visibility
                    idealEdgeLength: function(edge) {
                        return 250 * edgeLengthMultiplier;
                    },
                    // Lower elasticity = edges can stretch more
                    edgeElasticity: function(edge) { return 50; },
                    nestingFactor: 1.2,
                    // Low gravity = nodes spread more freely
                    gravity: 0.08,
                    // More iterations for better layout
                    numIter: 2000,
                    initialTemp: 400,
                    coolingFactor: 0.95,
                    minTemp: 1.0,
                    fit: true,
                    randomize: true,
                    // Space between disconnected components
                    componentSpacing: 200,
                    // Node overlap prevention
                    nodeOverlap: 50
                };
            }

            // ============================================================
            // Event Handlers
            // ============================================================

            function setupEventHandlers() {
                // Node drag events - save position after drag
                cy.on('dragfree', 'node', function(event) {
                    saveState();
                });

                // Pan and zoom - save state
                cy.on('pan zoom', debounce(function() {
                    if (!isDragging) {
                        saveState();
                    }
                }, 300));

                // Selection change
                cy.on('select unselect', 'node', function() {
                    updateSelectionBadge();
                });

                // Node hover - show tooltip
                cy.on('mouseover', 'node', function(event) {
                    const node = event.target;
                    showTooltip(event, buildNodeTooltip(node.data()));
                });

                cy.on('mouseout', 'node', function() {
                    hideTooltip();
                });

                // Edge hover - show tooltip
                cy.on('mouseover', 'edge', function(event) {
                    const edge = event.target;
                    showTooltip(event, buildEdgeTooltip(edge.data()));
                });

                cy.on('mouseout', 'edge', function() {
                    hideTooltip();
                });

                // Click on node without shift - focus and highlight neighbors
                cy.on('tap', 'node', function(event) {
                    if (!event.originalEvent.shiftKey) {
                        const node = event.target;
                        // Only focus if single selection
                        if (cy.$(':selected').length <= 1) {
                            focusNode(node.id());
                            elements.searchInput.value = node.data('label');
                            elements.clearSearch.classList.remove('hidden');
                        }
                    }
                });

                // Background click - deselect all and reset highlighting
                cy.on('tap', function(event) {
                    if (event.target === cy) {
                        cy.nodes().unselect();
                        resetHighlighting();
                        elements.searchInput.value = '';
                        elements.clearSearch.classList.add('hidden');
                        updateSelectionBadge();
                    }
                });

                // Mouse move - update tooltip position
                cy.on('mousemove', function(event) {
                    if (elements.tooltip.classList.contains('hidden')) return;
                    updateTooltipPosition(event.renderedPosition || event.position);
                });

                // Track dragging state
                cy.on('grab', function() {
                    isDragging = true;
                });

                cy.on('free', function() {
                    isDragging = false;
                });
            }

            function updateSelectionBadge() {
                const selected = cy.$(':selected').length;
                if (selected > 1) {
                    elements.selectionCount.textContent = selected;
                    elements.selectionBadge.classList.remove('hidden');
                } else {
                    elements.selectionBadge.classList.add('hidden');
                }
            }

            // ============================================================
            // Isolated Tables Sidebar
            // ============================================================

            function populateIsolatedSidebar(isolatedNodes) {
                if (isolatedNodes.length === 0) {
                    elements.isolatedSidebar.classList.add('hidden');
                    return;
                }

                elements.isolatedSidebar.classList.remove('hidden');
                elements.isolatedCount.textContent = isolatedNodes.length;
                renderIsolatedList(isolatedNodes);
            }

            function renderIsolatedList(nodes, filter = '') {
                const filterLower = filter.toLowerCase();
                const filteredNodes = filter
                    ? nodes.filter(n => n.data.label.toLowerCase().includes(filterLower))
                    : nodes;

                elements.isolatedList.innerHTML = filteredNodes.map(node => `
                    <div class="isolated-item px-3 py-2 rounded border border-slate-700 cursor-pointer text-sm hover:border-blue-500"
                         data-table="${escapeHtml(node.data.label)}">
                        <div class="font-medium text-slate-200 truncate">${escapeHtml(node.data.label)}</div>
                        <div class="text-xs text-slate-500 mt-0.5">${formatNumber(node.data.rowCount)} rows</div>
                    </div>
                `).join('');

                elements.isolatedList.querySelectorAll('.isolated-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const tableName = item.dataset.table;
                        elements.searchInput.value = tableName;
                        performSearch(tableName);
                    });
                });
            }

            // ============================================================
            // Search Functionality
            // ============================================================

            function performSearch(query) {
                if (!query.trim()) {
                    resetHighlighting();
                    elements.clearSearch.classList.add('hidden');
                    return;
                }

                elements.clearSearch.classList.remove('hidden');
                const searchTerm = query.toLowerCase().trim();

                const isIsolated = isolatedTables.some(t =>
                    t.data.label.toLowerCase() === searchTerm ||
                    t.data.label.toLowerCase().includes(searchTerm)
                );

                if (isIsolated && cy) {
                    cy.elements().addClass('dimmed');
                    elements.isolatedList.querySelectorAll('.isolated-item').forEach(item => {
                        if (item.dataset.table.toLowerCase().includes(searchTerm)) {
                            item.classList.add('bg-blue-900', 'border-blue-500');
                            item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        } else {
                            item.classList.remove('bg-blue-900', 'border-blue-500');
                        }
                    });
                    return;
                }

                elements.isolatedList.querySelectorAll('.isolated-item').forEach(item => {
                    item.classList.remove('bg-blue-900', 'border-blue-500');
                });

                if (!cy) return;

                const matchingNodes = cy.nodes().filter(node => {
                    const label = node.data('label').toLowerCase();
                    return label.includes(searchTerm);
                });

                if (matchingNodes.length === 0) {
                    cy.elements().addClass('dimmed');
                    return;
                }

                const primaryMatch = matchingNodes.first();
                focusNode(primaryMatch.id());
            }

            function focusNode(nodeId) {
                if (!cy) return;

                cy.elements().removeClass('highlighted neighbor dimmed');

                const targetNode = cy.getElementById(nodeId);
                if (!targetNode || targetNode.length === 0) return;

                const connectedEdges = targetNode.connectedEdges();
                const neighborNodes = targetNode.neighborhood('node');

                targetNode.addClass('highlighted');
                neighborNodes.addClass('neighbor');
                connectedEdges.addClass('highlighted');

                cy.elements().not(targetNode).not(neighborNodes).not(connectedEdges).addClass('dimmed');

                cy.animate({
                    center: { eles: targetNode },
                    zoom: Math.min(cy.zoom(), 1.5),
                    duration: 300
                });
            }

            function resetHighlighting() {
                if (cy) {
                    cy.elements().removeClass('highlighted neighbor dimmed');
                }
                elements.isolatedList.querySelectorAll('.isolated-item').forEach(item => {
                    item.classList.remove('bg-blue-900', 'border-blue-500');
                });
            }

            // ============================================================
            // Tooltip Functions
            // ============================================================

            function buildNodeTooltip(data) {
                const connectionText = data.connections === 1 ? 'connection' : 'connections';
                return `
                    <div class="font-semibold text-blue-400 mb-2">${escapeHtml(data.label)}</div>
                    <div class="text-sm text-slate-300 space-y-1">
                        <div><span class="text-slate-500">Rows:</span> ${formatNumber(data.rowCount)}</div>
                        <div><span class="text-slate-500">Links:</span> ${data.connections} ${connectionText}</div>
                    </div>
                `;
            }

            function buildEdgeTooltip(data) {
                const typeLabel = data.type === 'foreign_key' ? 'Foreign Key' : 'Trigger Dependency';
                const typeColor = data.type === 'foreign_key' ? 'text-blue-400' : 'text-red-400';

                let details = '';
                if (data.type === 'foreign_key' && data.columns) {
                    const columnPairs = data.columns.map(c =>
                        `<div class="ml-2">${escapeHtml(c.child)} → ${escapeHtml(c.parent)}</div>`
                    ).join('');
                    details = `<div class="mt-2 text-xs"><div class="text-slate-500">Columns:</div>${columnPairs}</div>`;
                } else if (data.type === 'trigger' && data.triggers) {
                    const triggerList = data.triggers.map(t =>
                        `<div class="ml-2">${escapeHtml(t)}</div>`
                    ).join('');
                    details = `<div class="mt-2 text-xs"><div class="text-slate-500">Triggers:</div>${triggerList}</div>`;
                }

                return `
                    <div class="font-semibold ${typeColor} mb-2">${typeLabel}</div>
                    <div class="text-sm text-slate-300">
                        <div>${escapeHtml(data.source)} → ${escapeHtml(data.target)}</div>
                    </div>
                    ${details}
                `;
            }

            function showTooltip(event, content) {
                elements.tooltip.innerHTML = content;
                elements.tooltip.classList.remove('hidden');
                updateTooltipPosition(event.renderedPosition || event.position);
            }

            function hideTooltip() {
                elements.tooltip.classList.add('hidden');
            }

            function updateTooltipPosition(pos) {
                const containerRect = elements.cy.getBoundingClientRect();
                const tooltipRect = elements.tooltip.getBoundingClientRect();

                let x = pos.x + containerRect.left + 15;
                let y = pos.y + containerRect.top + 15;

                if (x + tooltipRect.width > window.innerWidth - 20) {
                    x = pos.x + containerRect.left - tooltipRect.width - 15;
                }
                if (y + tooltipRect.height > window.innerHeight - 20) {
                    y = pos.y + containerRect.top - tooltipRect.height - 15;
                }

                elements.tooltip.style.left = `${x}px`;
                elements.tooltip.style.top = `${y}px`;
            }

            // ============================================================
            // UI Updates
            // ============================================================

            function showLoading(show) {
                elements.loadingOverlay.classList.toggle('hidden', !show);
            }

            function showError(message) {
                elements.errorMessage.textContent = message;
                elements.errorOverlay.classList.remove('hidden');
                elements.loadingOverlay.classList.add('hidden');
            }

            function hideError() {
                elements.errorOverlay.classList.add('hidden');
            }

            function updateStats(data, connectedCount, isolatedCount) {
                elements.statTables.textContent = data.meta.tableCount;
                elements.statConnected.textContent = connectedCount;
                elements.statFk.textContent = data.meta.fkCount;
                elements.statTriggers.textContent = data.meta.triggerDepCount;
            }

            function updateDbInfo(meta) {
                const dbTypeLabels = { 'mysql': 'MySQL', 'oci': 'Oracle', 'sqlsrv': 'SQL Server' };
                const label = dbTypeLabels[meta.dbType] || meta.dbType.toUpperCase();
                elements.dbInfo.textContent = `${label}: ${meta.database || 'Connected'}`;
                elements.dbInfo.classList.remove('hidden');
            }

            // ============================================================
            // Export & Layout Functions
            // ============================================================

            function exportPNG() {
                if (!cy) return;

                const dbName = schemaData?.meta?.database || 'schema';
                const timestamp = new Date().toISOString().split('T')[0];
                const filename = `${dbName}_schema_${timestamp}.png`;

                try {
                    // Get PNG as base64 data URL
                    const pngData = cy.png({
                        output: 'base64uri',
                        bg: '#0F172A',
                        scale: 3,
                        full: true
                    });

                    // Convert base64 to blob
                    const byteString = atob(pngData.split(',')[1]);
                    const mimeString = pngData.split(',')[0].split(':')[1].split(';')[0];
                    const ab = new ArrayBuffer(byteString.length);
                    const ia = new Uint8Array(ab);
                    for (let i = 0; i < byteString.length; i++) {
                        ia[i] = byteString.charCodeAt(i);
                    }
                    const blob = new Blob([ab], { type: mimeString });

                    // Download
                    saveAs(blob, filename);
                } catch (e) {
                    console.error('Export failed:', e);
                    alert('Export failed: ' + e.message);
                }
            }

            function runLayout() {
                if (!cy) return;

                clearSavedState();

                const nodeCount = cy.nodes().length;
                const edgeCount = cy.edges().length;

                cy.layout(getLayoutConfig(nodeCount, edgeCount)).run();

                setTimeout(() => {
                    cy.fit(undefined, 50);
                    saveState();
                }, 100);
            }

            function fitToScreen() {
                if (!cy) return;
                cy.animate({
                    fit: { padding: 50 },
                    duration: 300
                });
            }

            function resetPositions() {
                clearSavedState();
                runLayout();
            }

            // ============================================================
            // Utility Functions
            // ============================================================

            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function formatNumber(num) {
                if (num === null || num === undefined) return 'N/A';
                return Number(num).toLocaleString();
            }

            function debounce(func, wait) {
                let timeout;
                return function(...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                };
            }

            // ============================================================
            // Event Bindings
            // ============================================================

            elements.searchInput.addEventListener('input', debounce(function(e) {
                performSearch(e.target.value);
            }, 200));

            elements.searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    resetHighlighting();
                    elements.clearSearch.classList.add('hidden');
                }
            });

            elements.isolatedSearch.addEventListener('input', debounce(function(e) {
                renderIsolatedList(isolatedTables, e.target.value);
            }, 150));

            elements.clearSearch.addEventListener('click', function() {
                elements.searchInput.value = '';
                resetHighlighting();
                this.classList.add('hidden');
            });

            elements.btnFit.addEventListener('click', fitToScreen);
            elements.btnRelayout.addEventListener('click', runLayout);
            elements.btnExport.addEventListener('click', exportPNG);
            elements.btnRetry.addEventListener('click', loadSchemaData);
            elements.btnResetPositions.addEventListener('click', resetPositions);

            elements.btnZoomIn.addEventListener('click', function() {
                if (cy) cy.zoom(cy.zoom() * 1.3);
            });

            elements.btnZoomOut.addEventListener('click', function() {
                if (cy) cy.zoom(cy.zoom() / 1.3);
            });

            elements.btnClearSelection.addEventListener('click', function() {
                if (cy) {
                    cy.nodes().unselect();
                    updateSelectionBadge();
                }
            });

            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    elements.searchInput.focus();
                    elements.searchInput.select();
                }

                if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                    e.preventDefault();
                    exportPNG();
                }

                if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                    e.preventDefault();
                    if (cy) {
                        cy.nodes().select();
                        updateSelectionBadge();
                    }
                }

                if (e.key === 'Escape' && document.activeElement !== elements.searchInput) {
                    if (cy) {
                        cy.nodes().unselect();
                        updateSelectionBadge();
                    }
                    resetHighlighting();
                    fitToScreen();
                }
            });

            window.addEventListener('resize', debounce(function() {
                if (cy) cy.resize();
            }, 100));

            // ============================================================
            // Initialize Application
            // ============================================================

            document.addEventListener('DOMContentLoaded', loadSchemaData);

        })();
    </script>
</body>
</html>
