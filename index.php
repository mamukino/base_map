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

        <!-- Main Content Area -->
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
                        <div class="w-6 h-6 rounded bg-slate-700 border-2 border-slate-500"></div>
                        <span class="text-sm">Table</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-6 h-6 rounded bg-slate-800 border-2 border-slate-600 opacity-50"></div>
                        <span class="text-sm">Isolated Table</span>
                    </div>
                </div>
            </div>

            <!-- Stats Panel -->
            <div class="floating-panel absolute bottom-4 right-4 rounded-xl border border-slate-700 p-4 z-40">
                <h3 class="font-semibold mb-3 text-sm uppercase tracking-wider text-slate-400">Statistics</h3>
                <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                    <div class="text-slate-400">Tables:</div>
                    <div id="stat-tables" class="font-mono text-right">-</div>
                    <div class="text-slate-400">Foreign Keys:</div>
                    <div id="stat-fk" class="font-mono text-right text-blue-400">-</div>
                    <div class="text-slate-400">Trigger Deps:</div>
                    <div id="stat-triggers" class="font-mono text-right text-red-400">-</div>
                    <div class="text-slate-400">Isolated:</div>
                    <div id="stat-isolated" class="font-mono text-right text-slate-500">-</div>
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

    <script>
        // ============================================================
        // Database Schema Visualizer - Main Application
        // ============================================================

        (function() {
            'use strict';

            // Global state
            let cy = null;
            let schemaData = null;
            let searchTimeout = null;

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
                statFk: document.getElementById('stat-fk'),
                statTriggers: document.getElementById('stat-triggers'),
                statIsolated: document.getElementById('stat-isolated'),
                btnFit: document.getElementById('btn-fit'),
                btnRelayout: document.getElementById('btn-relayout'),
                btnExport: document.getElementById('btn-export'),
                btnRetry: document.getElementById('btn-retry'),
                btnZoomIn: document.getElementById('btn-zoom-in'),
                btnZoomOut: document.getElementById('btn-zoom-out')
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
                        'border-color': '#64748B',
                        'color': '#F1F5F9',
                        'font-size': '12px',
                        'font-weight': 500,
                        'text-wrap': 'wrap',
                        'text-max-width': '120px',
                        'width': 'label',
                        'height': 'label',
                        'padding': '14px',
                        'shape': 'roundrectangle',
                        'transition-property': 'background-color, border-color, opacity',
                        'transition-duration': '0.2s'
                    }
                },
                // Isolated nodes (no connections)
                {
                    selector: 'node[?isolated]',
                    style: {
                        'background-color': '#1E293B',
                        'border-color': '#475569',
                        'border-style': 'dashed',
                        'color': '#94A3B8'
                    }
                },
                // Connected nodes (have relationships)
                {
                    selector: 'node[connections > 0]',
                    style: {
                        'background-color': '#334155',
                        'border-color': '#3B82F6'
                    }
                },
                // Highly connected nodes
                {
                    selector: 'node[connections >= 5]',
                    style: {
                        'background-color': '#1E3A5F',
                        'border-color': '#60A5FA',
                        'border-width': 3
                    }
                },
                // Highlighted node (search result)
                {
                    selector: 'node.highlighted',
                    style: {
                        'background-color': '#1D4ED8',
                        'border-color': '#60A5FA',
                        'border-width': 3,
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
                        'opacity': 0.2
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
                        'width': 3,
                        'z-index': 999
                    }
                },
                // Dimmed edges
                {
                    selector: 'edge.dimmed',
                    style: {
                        'opacity': 0.1
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
                    initializeCytoscape(data);
                    updateStats(data);
                    updateDbInfo(data.meta);
                    showLoading(false);

                } catch (error) {
                    console.error('Failed to load schema data:', error);
                    showError(error.message);
                }
            }

            // ============================================================
            // Cytoscape Initialization
            // ============================================================

            function initializeCytoscape(data) {
                // Destroy existing instance
                if (cy) {
                    cy.destroy();
                }

                // Create Cytoscape instance
                cy = cytoscape({
                    container: elements.cy,
                    elements: [...data.nodes, ...data.edges],
                    style: cytoscapeStyles,
                    layout: {
                        name: 'dagre',
                        rankDir: 'TB',
                        nodeSep: 80,
                        edgeSep: 50,
                        rankSep: 100,
                        padding: 50,
                        animate: true,
                        animationDuration: 500,
                        fit: true
                    },
                    minZoom: 0.1,
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
                if (!cy || !query.trim()) {
                    resetHighlighting();
                    elements.clearSearch.classList.add('hidden');
                    return;
                }

                elements.clearSearch.classList.remove('hidden');
                const searchTerm = query.toLowerCase().trim();

                // Find matching nodes
                const matchingNodes = cy.nodes().filter(node => {
                    const label = node.data('label').toLowerCase();
                    return label.includes(searchTerm);
                });

                if (matchingNodes.length === 0) {
                    // No matches - dim everything slightly
                    cy.elements().addClass('dimmed');
                    return;
                }

                // Get the first (best) match and focus on it
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
                if (!cy) return;
                cy.elements().removeClass('highlighted neighbor dimmed');
            }

            // ============================================================
            // Tooltip Functions
            // ============================================================

            function buildNodeTooltip(data) {
                const connectionText = data.connections === 1 ? 'connection' : 'connections';
                const isolatedBadge = data.isolated ?
                    '<span class="inline-block px-2 py-0.5 bg-slate-600 text-slate-300 text-xs rounded mt-1">Isolated</span>' : '';

                return `
                    <div class="font-semibold text-blue-400 mb-2">${escapeHtml(data.label)}</div>
                    <div class="text-sm text-slate-300 space-y-1">
                        <div><span class="text-slate-500">Rows:</span> ${formatNumber(data.rowCount)}</div>
                        <div><span class="text-slate-500">Links:</span> ${data.connections} ${connectionText}</div>
                    </div>
                    ${isolatedBadge}
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

            function updateStats(data) {
                elements.statTables.textContent = data.meta.tableCount;
                elements.statFk.textContent = data.meta.fkCount;
                elements.statTriggers.textContent = data.meta.triggerDepCount;

                const isolatedCount = data.nodes.filter(n => n.data.isolated).length;
                elements.statIsolated.textContent = isolatedCount;
            }

            function updateDbInfo(meta) {
                const dbTypeLabels = {
                    'mysql': 'MySQL',
                    'oci': 'Oracle',
                    'sqlsrv': 'SQL Server'
                };
                const label = dbTypeLabels[meta.dbType] || meta.dbType.toUpperCase();
                elements.dbInfo.textContent = `${label}: ${meta.database}`;
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

                cy.layout({
                    name: 'dagre',
                    rankDir: 'TB',
                    nodeSep: 80,
                    edgeSep: 50,
                    rankSep: 100,
                    padding: 50,
                    animate: true,
                    animationDuration: 500,
                    fit: true
                }).run();
            }

            function fitToScreen() {
                if (!cy) return;
                cy.animate({
                    fit: { padding: 50 },
                    duration: 300
                });
            }

            // ============================================================
            // Utility Functions
            // ============================================================

            function escapeHtml(text) {
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

            // Search input
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
