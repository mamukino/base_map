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
                <span id="db-info" class="text-sm text-slate-400 hidden">
                    <!-- Populated dynamically -->
                </span>
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
                    <button
                        id="clear-search"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200 hidden"
                        title="Clear search"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2">
                    <button
                        id="btn-fit"
                        class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                        title="Fit to screen"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                        </svg>
                    </button>
                    <button
                        id="btn-relayout"
                        class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors"
                        title="Re-layout graph"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                    <button
                        id="btn-export"
                        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded-lg transition-colors font-medium"
                        title="Export as PNG"
                    >
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
                <!-- Sidebar Header -->
                <div class="p-4 border-b border-slate-700">
                    <div class="flex items-center gap-2 text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <h2 class="font-semibold text-sm uppercase tracking-wider">Isolated Tables</h2>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Tables with no relationships</p>
                </div>

                <!-- Sidebar Search -->
                <div class="p-3 border-b border-slate-700">
                    <input
                        type="text"
                        id="isolated-search"
                        placeholder="Filter isolated..."
                        class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                </div>

                <!-- Table List -->
                <div id="isolated-list" class="flex-1 overflow-y-auto p-2 space-y-1">
                    <!-- Populated dynamically -->
                </div>

                <!-- Sidebar Footer -->
                <div class="p-3 border-t border-slate-700 text-xs text-slate-500">
                    <span id="isolated-count">0</span> isolated tables
                </div>
            </div>

            <!-- Graph Container -->
            <div class="flex-1 relative overflow-hidden">
                <!-- Cytoscape Container -->
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
                        <button
                            id="btn-retry"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded-lg transition-colors"
                        >
                            Retry
                        </button>
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
                            <div class="w-6 h-6 rounded bg-slate-700 border-2 border-blue-400 border-opacity-50"></div>
                            <span class="text-sm">Highly Connected (5+)</span>
                        </div>
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

                <!-- Tooltip -->
                <div id="tooltip" class="tooltip hidden"></div>
            </div>
        </div>
    </div>

    <script>
        // ============================================================
        // Database Schema Visualizer - Main Application
        // ============================================================

        (function() {
            'use strict';

            // Global state
            let cy = null;
            let schemaData = null;
            let isolatedTables = [];

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
                isolatedSidebar: document.getElementById('isolated-sidebar'),
                isolatedList: document.getElementById('isolated-list'),
                isolatedCount: document.getElementById('isolated-count'),
                isolatedSearch: document.getElementById('isolated-search')
            };

            // ============================================================
            // Cytoscape Styles
            // ============================================================

            const cytoscapeStyles = [
                // Base node style
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
                // Highly connected nodes (5+ connections)
                {
                    selector: 'node[connections >= 5]',
                    style: {
                        'background-color': '#1E3A5F',
                        'border-color': '#60A5FA',
                        'border-width': 3,
                        'font-weight': 600
                    }
                },
                // Highlighted node (search result)
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
                // Neighbor of highlighted node
                {
                    selector: 'node.neighbor',
                    style: {
                        'background-color': '#475569',
                        'border-color': '#94A3B8',
                        'border-width': 2,
                        'z-index': 998
                    }
                },
                // Dimmed nodes (not in search)
                {
                    selector: 'node.dimmed',
                    style: {
                        'opacity': 0.15
                    }
                },
                // Hovered node
                {
                    selector: 'node:active',
                    style: {
                        'overlay-color': '#3B82F6',
                        'overlay-padding': 8,
                        'overlay-opacity': 0.2
                    }
                },
                // Base edge style
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
                // Foreign Key edges (solid blue)
                {
                    selector: 'edge[type = "foreign_key"]',
                    style: {
                        'line-color': '#3B82F6',
                        'target-arrow-color': '#3B82F6',
                        'line-style': 'solid',
                        'width': 2
                    }
                },
                // Trigger dependency edges (dashed red)
                {
                    selector: 'edge[type = "trigger"]',
                    style: {
                        'line-color': '#EF4444',
                        'target-arrow-color': '#EF4444',
                        'line-style': 'dashed',
                        'width': 2
                    }
                },
                // Highlighted edges
                {
                    selector: 'edge.highlighted',
                    style: {
                        'width': 4,
                        'z-index': 999
                    }
                },
                // Dimmed edges
                {
                    selector: 'edge.dimmed',
                    style: {
                        'opacity': 0.08
                    }
                }
            ];

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

                    // Separate isolated and connected tables
                    const { connectedNodes, isolatedNodes } = separateNodes(data);
                    isolatedTables = isolatedNodes;

                    // Initialize graph with only connected nodes
                    initializeCytoscape(connectedNodes, data.edges);

                    // Populate isolated tables sidebar
                    populateIsolatedSidebar(isolatedNodes);

                    updateStats(data, connectedNodes.length, isolatedNodes.length);
                    updateDbInfo(data.meta);
                    showLoading(false);

                } catch (error) {
                    console.error('Failed to load schema data:', error);
                    showError(error.message);
                }
            }

            /**
             * Separate nodes into connected and isolated
             */
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

                // Sort isolated nodes alphabetically
                isolatedNodes.sort((a, b) => a.data.label.localeCompare(b.data.label));

                return { connectedNodes, isolatedNodes };
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
                        <div class="text-xs text-slate-500 mt-0.5">
                            ${formatNumber(node.data.rowCount)} rows
                        </div>
                    </div>
                `).join('');

                // Add click handlers
                elements.isolatedList.querySelectorAll('.isolated-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const tableName = item.dataset.table;
                        elements.searchInput.value = tableName;
                        performSearch(tableName);
                    });
                });
            }

            // ============================================================
            // Cytoscape Initialization
            // ============================================================

            function initializeCytoscape(nodes, edges) {
                // Destroy existing instance
                if (cy) {
                    cy.destroy();
                }

                // Calculate dynamic spacing based on node count
                const nodeCount = nodes.length;
                const edgeCount = edges.length;

                // More nodes/edges = more spacing needed
                const baseSep = 60;
                const baseRank = 120;

                // Increase spacing for complex graphs
                const complexityFactor = Math.min(2.5, 1 + (edgeCount / nodeCount) * 0.3);
                const nodeSep = Math.round(baseSep * complexityFactor);
                const rankSep = Math.round(baseRank * complexityFactor);

                // Create Cytoscape instance
                cy = cytoscape({
                    container: elements.cy,
                    elements: [...nodes, ...edges],
                    style: cytoscapeStyles,
                    layout: {
                        name: 'dagre',
                        rankDir: 'TB',
                        nodeSep: nodeSep,
                        edgeSep: 40,
                        rankSep: rankSep,
                        padding: 60,
                        animate: true,
                        animationDuration: 500,
                        fit: true,
                        spacingFactor: 1.2
                    },
                    minZoom: 0.05,
                    maxZoom: 4,
                    wheelSensitivity: 0.3,
                    boxSelectionEnabled: false
                });

                // Setup event handlers
                setupEventHandlers();
            }

            // ============================================================
            // Event Handlers
            // ============================================================

            function setupEventHandlers() {
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

                // Node click - focus and show neighbors
                cy.on('tap', 'node', function(event) {
                    const node = event.target;
                    focusNode(node.id());
                    elements.searchInput.value = node.data('label');
                    elements.clearSearch.classList.remove('hidden');
                });

                // Background click - reset view
                cy.on('tap', function(event) {
                    if (event.target === cy) {
                        resetHighlighting();
                        elements.searchInput.value = '';
                        elements.clearSearch.classList.add('hidden');
                    }
                });

                // Mouse move - update tooltip position
                cy.on('mousemove', function(event) {
                    if (elements.tooltip.classList.contains('hidden')) return;
                    updateTooltipPosition(event.renderedPosition || event.position);
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

                // Check if it's an isolated table
                const isIsolated = isolatedTables.some(t =>
                    t.data.label.toLowerCase() === searchTerm ||
                    t.data.label.toLowerCase().includes(searchTerm)
                );

                if (isIsolated && cy) {
                    // Dim all nodes when searching for isolated table
                    cy.elements().addClass('dimmed');

                    // Highlight in sidebar
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

                // Clear sidebar highlights
                elements.isolatedList.querySelectorAll('.isolated-item').forEach(item => {
                    item.classList.remove('bg-blue-900', 'border-blue-500');
                });

                if (!cy) return;

                // Find matching nodes in graph
                const matchingNodes = cy.nodes().filter(node => {
                    const label = node.data('label').toLowerCase();
                    return label.includes(searchTerm);
                });

                if (matchingNodes.length === 0) {
                    cy.elements().addClass('dimmed');
                    return;
                }

                // Focus on the first match
                const primaryMatch = matchingNodes.first();
                focusNode(primaryMatch.id());
            }

            function focusNode(nodeId) {
                if (!cy) return;

                // Reset previous highlighting
                cy.elements().removeClass('highlighted neighbor dimmed');

                // Get target node
                const targetNode = cy.getElementById(nodeId);
                if (!targetNode || targetNode.length === 0) return;

                // Get connected edges and neighbor nodes
                const connectedEdges = targetNode.connectedEdges();
                const neighborNodes = targetNode.neighborhood('node');

                // Apply highlighting
                targetNode.addClass('highlighted');
                neighborNodes.addClass('neighbor');
                connectedEdges.addClass('highlighted');

                // Dim everything else
                cy.elements().not(targetNode).not(neighborNodes).not(connectedEdges).addClass('dimmed');

                // Center on the node
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

                // Clear sidebar highlights
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

                // Keep tooltip within viewport
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
                const dbTypeLabels = {
                    'mysql': 'MySQL',
                    'oci': 'Oracle',
                    'sqlsrv': 'SQL Server'
                };
                const label = dbTypeLabels[meta.dbType] || meta.dbType.toUpperCase();
                elements.dbInfo.textContent = `${label}: ${meta.database || 'Connected'}`;
                elements.dbInfo.classList.remove('hidden');
            }

            // ============================================================
            // Export Functionality
            // ============================================================

            function exportPNG() {
                if (!cy) return;

                // Generate high-res PNG
                const png = cy.png({
                    output: 'blob',
                    bg: '#0F172A',
                    scale: 3,
                    full: true
                });

                // Generate filename
                const dbName = schemaData?.meta?.database || 'schema';
                const timestamp = new Date().toISOString().split('T')[0];
                const filename = `${dbName}_schema_${timestamp}.png`;

                // Save using FileSaver
                saveAs(png, filename);
            }

            // ============================================================
            // Layout Functions
            // ============================================================

            function runLayout() {
                if (!cy) return;

                const nodeCount = cy.nodes().length;
                const edgeCount = cy.edges().length;

                const complexityFactor = Math.min(2.5, 1 + (edgeCount / Math.max(1, nodeCount)) * 0.3);
                const nodeSep = Math.round(60 * complexityFactor);
                const rankSep = Math.round(120 * complexityFactor);

                cy.layout({
                    name: 'dagre',
                    rankDir: 'TB',
                    nodeSep: nodeSep,
                    edgeSep: 40,
                    rankSep: rankSep,
                    padding: 60,
                    animate: true,
                    animationDuration: 500,
                    fit: true,
                    spacingFactor: 1.2
                }).run();
            }

            function fitToScreen() {
                if (!cy) return;
                cy.animate({
                    fit: { padding: 60 },
                    duration: 300
                });
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

            // Main search input
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

            // Isolated sidebar search
            elements.isolatedSearch.addEventListener('input', debounce(function(e) {
                renderIsolatedList(isolatedTables, e.target.value);
            }, 150));

            // Clear search button
            elements.clearSearch.addEventListener('click', function() {
                elements.searchInput.value = '';
                resetHighlighting();
                this.classList.add('hidden');
            });

            // Action buttons
            elements.btnFit.addEventListener('click', fitToScreen);
            elements.btnRelayout.addEventListener('click', runLayout);
            elements.btnExport.addEventListener('click', exportPNG);
            elements.btnRetry.addEventListener('click', loadSchemaData);

            // Zoom buttons
            elements.btnZoomIn.addEventListener('click', function() {
                if (cy) cy.zoom(cy.zoom() * 1.3);
            });

            elements.btnZoomOut.addEventListener('click', function() {
                if (cy) cy.zoom(cy.zoom() / 1.3);
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + F = Focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    elements.searchInput.focus();
                    elements.searchInput.select();
                }

                // Ctrl/Cmd + E = Export
                if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                    e.preventDefault();
                    exportPNG();
                }

                // Escape = Reset view
                if (e.key === 'Escape' && document.activeElement !== elements.searchInput) {
                    resetHighlighting();
                    fitToScreen();
                }
            });

            // Window resize
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
