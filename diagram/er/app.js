document.addEventListener('DOMContentLoaded', () => {
    // UI Elements
    const container = document.getElementById('mynetwork');
    const loadingEl = document.getElementById('loading');
    const toggleColorBtn = document.getElementById('toggle-color-btn');
    const printBtn = document.getElementById('print-btn');
    const resetBtn = document.getElementById('reset-btn');

    let network = null;
    let savedPositions = null;

    try {
        const savedPosStr = localStorage.getItem('nibash_er_positions');
        if (savedPosStr) {
            savedPositions = JSON.parse(savedPosStr);
        }
    } catch(e) {}

    // Helper to save positions
    function savePositions() {
        if (!network) return;
        const pos = network.getPositions();
        localStorage.setItem('nibash_er_positions', JSON.stringify(pos));
    }

    // Helper to guess relationships
    function guessTargetTable(fieldName, tables) {
        if (!fieldName.endsWith('_id') || fieldName === 'id') return null;
        let base = fieldName.replace('_id', '');
        
        if (base === 'apt') return 'apartments';
        if (base === 'visit') return 'guest_passes';
        
        let target1 = base + 's';
        let target2 = base + 'es';
        let target3 = base.endsWith('y') ? base.slice(0, -1) + 'ies' : '';
        
        if (tables.includes(target1)) return target1;
        if (tables.includes(target2)) return target2;
        if (target3 && tables.includes(target3)) return target3;
        if (tables.includes(base)) return base;
        
        return null;
    }

    try {
        const schema = fullSchema;
        const nodes = new vis.DataSet();
        const edges = new vis.DataSet();
        const tableNames = Object.keys(schema);

        // Helper to get saved pos
        function getPos(id) {
            if (savedPositions && savedPositions[id]) {
                return { x: savedPositions[id].x, y: savedPositions[id].y };
            }
            return { x: undefined, y: undefined };
        }

        let tableIndex = 0;

        // Add invisible horizontal spine anchors
        nodes.add([
            { id: 'ANCHOR_L', hidden: true, x: -600, y: 0, fixed: true },
            { id: 'ANCHOR_C', hidden: true, x: 0, y: 0, fixed: true },
            { id: 'ANCHOR_R', hidden: true, x: 600, y: 0, fixed: true }
        ]);

        tableNames.forEach(tableName => {
            let tPos = getPos('T_' + tableName);
            
            // Distribute tables across the 3 anchors for landscape stretch
            let anchorId = 'ANCHOR_C';
            if (tableIndex % 3 === 0) anchorId = 'ANCHOR_L';
            else if (tableIndex % 3 === 2) anchorId = 'ANCHOR_R';
            tableIndex++;

            // Tether table tightly to its anchor
            edges.add({
                from: anchorId,
                to: 'T_' + tableName,
                hidden: true,
                length: 50 // Short tether for dense packing
            });

            // Entity Node
            const tColor = { background: '#dbeafe', border: '#2563eb' }; // soft blue
            const tFontColor = '#1e3a8a';
            nodes.add({
                id: 'T_' + tableName,
                label: tableName.toUpperCase(),
                shape: 'box',
                color: tColor,
                font: { bold: true, size: 18, color: tFontColor },
                x: tPos.x,
                y: tPos.y,
                originalColor: tColor,
                originalFontColor: tFontColor
            });

            const columns = schema[tableName];
            columns.forEach(col => {
                const isPk = col.Key === 'PRI';
                const colId = 'C_' + tableName + '_' + col.Field;
                let cPos = getPos(colId);
                
                // Attribute Node - Aesthetically pleasing soft pastel colors
                const cColor = isPk 
                    ? { background: '#f3e8ff', border: '#7e22ce' } // soft purple for PK
                    : { background: '#d1fae5', border: '#059669' }; // soft mint green for regular
                const cFontColor = isPk ? '#581c87' : '#064e3b';
                
                nodes.add({
                    id: colId,
                    label: (isPk ? '🔑 ' : '') + col.Field,
                    shape: 'ellipse',
                    color: cColor,
                    font: { size: 14, color: cFontColor },
                    size: 10,
                    x: cPos.x,
                    y: cPos.y,
                    originalColor: cColor,
                    originalFontColor: cFontColor
                });

                edges.add({
                    id: 'E_TC_' + tableName + '_' + col.Field,
                    from: 'T_' + tableName,
                    to: colId,
                    color: { color: '#9ca3af' },
                    length: 40,
                    width: 1,
                    originalColor: { color: '#9ca3af' }
                });

                // Check for Relationship
                const targetTable = guessTargetTable(col.Field, tableNames);
                if (targetTable) {
                    const relId = 'R_' + tableName + '_' + targetTable + '_' + col.Field;
                    let rPos = getPos(relId);
                    
                    if (!nodes.get(relId)) {
                        const rColor = { background: '#f1f5f9', border: '#475569' }; // soft slate gray
                        const rFontColor = '#1e293b';
                        nodes.add({
                            id: relId,
                            label: col.Field,
                            shape: 'box',
                            color: rColor,
                            font: { size: 12, color: rFontColor },
                            x: rPos.x,
                            y: rPos.y,
                            originalColor: rColor,
                            originalFontColor: rFontColor
                        });

                        edges.add({
                            id: 'E_R1_' + relId,
                            from: 'T_' + tableName,
                            to: relId,
                            color: { color: '#64748b' },
                            width: 2,
                            originalColor: { color: '#64748b' }
                        });

                        edges.add({
                            id: 'E_R2_' + relId,
                            from: relId,
                            to: 'T_' + targetTable,
                            color: { color: '#64748b' },
                            width: 2,
                            originalColor: { color: '#64748b' }
                        });
                    }
                }
            });
        });

        const data = { nodes: nodes, edges: edges };
        const options = {
            physics: {
                enabled: !savedPositions, // Disable if we loaded from local storage
                barnesHut: {
                    gravitationalConstant: -1500,
                    centralGravity: 0.8, // Strong inward pull to congest nodes
                    springLength: 30, // Short springs to keep it dense
                    springConstant: 0.05,
                    damping: 0.09,
                    avoidOverlap: 0.5 // Keep enough repulsion so they don't sit directly on top of each other
                },
                stabilization: {
                    iterations: 2000,
                    updateInterval: 100,
                    fit: true
                }
            },
            edges: { smooth: { type: 'continuous' } },
            interaction: {
                hover: true,
                zoomView: true,
                dragView: true
            }
        };

        network = new vis.Network(container, data, options);

        if (savedPositions) {
            loadingEl.style.display = 'none';
        } else {
            network.once("stabilizationIterationsDone", function () {
                loadingEl.style.display = 'none';
                network.setOptions({ physics: { enabled: false } });
                network.fit();
                savePositions();
            });
        }

        // --- Dragging Logic ---
        let draggingChildNodes = [];
        let dragRelativePos = {};

        network.on("dragStart", function(params) {
            if (params.nodes.length > 0) {
                let nodeId = params.nodes[0];
                if (nodeId.startsWith('T_')) {
                    let tableName = nodeId.substring(2);
                    let connectedNodesIds = network.getConnectedNodes(nodeId);
                    
                    draggingChildNodes = connectedNodesIds.filter(id => 
                        id.startsWith('C_' + tableName + '_') || 
                        id.startsWith('R_' + tableName + '_')
                    );
                    
                    let allPositions = network.getPositions([nodeId, ...draggingChildNodes]);
                    let nodePos = allPositions[nodeId];
                    
                    dragRelativePos = {};
                    draggingChildNodes.forEach(id => {
                        dragRelativePos[id] = {
                            dx: allPositions[id].x - nodePos.x,
                            dy: allPositions[id].y - nodePos.y
                        };
                    });
                }
            }
        });

        network.on("dragging", function(params) {
            if (params.nodes.length > 0 && draggingChildNodes.length > 0) {
                let nodeId = params.nodes[0];
                let nodePos = network.getPositions([nodeId])[nodeId];
                
                let updates = draggingChildNodes.map(id => ({
                    id: id,
                    x: nodePos.x + dragRelativePos[id].dx,
                    y: nodePos.y + dragRelativePos[id].dy
                }));
                
                nodes.update(updates);
            }
        });

        network.on("dragEnd", function() {
            draggingChildNodes = [];
            dragRelativePos = {};
            savePositions();
        });

        // Reusable function to set color mode
        let isBw = false;
        function setColorMode(bw) {
            isBw = bw;
            let nodeUpdates = [];
            nodes.forEach(node => {
                if (isBw) {
                    nodeUpdates.push({
                        id: node.id,
                        color: { background: '#ffffff', border: '#000000' },
                        font: { color: '#000000' }
                    });
                } else {
                    nodeUpdates.push({
                        id: node.id,
                        color: node.originalColor,
                        font: { color: node.originalFontColor }
                    });
                }
            });
            nodes.update(nodeUpdates);

            let edgeUpdates = [];
            edges.forEach(edge => {
                if (edge.hidden) return;
                if (isBw) {
                    edgeUpdates.push({
                        id: edge.id,
                        color: { color: '#000000' }
                    });
                } else {
                    edgeUpdates.push({
                        id: edge.id,
                        color: edge.originalColor
                    });
                }
            });
            edges.update(edgeUpdates);
        }

        // Toggle True Black & White Mode
        if (toggleColorBtn) {
            toggleColorBtn.addEventListener('click', () => {
                setColorMode(!isBw);
            });
        }

        // Print Diagram
        if (printBtn) {
            printBtn.addEventListener('click', () => {
                // Force B&W mode right before printing to prevent gray backgrounds
                const wasColor = !isBw;
                if (wasColor) {
                    setColorMode(true);
                }

                if (network) {
                    network.fit();
                    setTimeout(() => {
                        window.print();
                        // Revert back to color after printing dialog closes
                        if (wasColor) {
                            setColorMode(false);
                        }
                    }, 500);
                } else {
                    window.print();
                }
            });
        }
        
        // Reset Layout
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                localStorage.removeItem('nibash_er_positions');
                window.location.reload();
            });
        }

    } catch (err) {
        console.error(err);
        loadingEl.innerHTML = `<div class="text-red-500 font-bold bg-white p-4 rounded shadow border border-red-200">Error generating diagram: ${err.message}</div>`;
    }
});
