document.addEventListener('DOMContentLoaded', () => {
    const plane = document.getElementById('diagram-plane');
    const svgLayer = document.getElementById('svg-layer');
    const canvasContainer = document.getElementById('canvas-container');
    
    // UI Buttons
    const resetBtn = document.getElementById('reset-btn');
    const toggleColorBtn = document.getElementById('toggle-color-btn');
    const printBtn = document.getElementById('print-btn');

    // State
    let scale = 1;
    let panX = 0;
    let panY = 0;
    let isPanning = false;
    let startPanX = 0;
    let startPanY = 0;

    let isDraggingNode = null;
    let dragStartX = 0;
    let dragStartY = 0;

    let relationships = []; // Store lines to draw

    // Load saved positions or init
    let positions = JSON.parse(localStorage.getItem('nibash_schema_positions')) || {};

    // Helper: guess relationship
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

    // Initialize Schema
    function initSchema() {
        const tableNames = Object.keys(fullSchema);
        let defaultX = 50;
        let defaultY = 50;
        let maxYInRow = 0;

        tableNames.forEach((tableName, index) => {
            const columns = fullSchema[tableName];
            
            // Set position
            let posX = positions[tableName]?.x;
            let posY = positions[tableName]?.y;
            if (posX === undefined) {
                posX = defaultX;
                posY = defaultY;
                positions[tableName] = { x: posX, y: posY };
                
                defaultX += 350; // column width roughly 300 + gap 50
                if (defaultX > 2000) {
                    defaultX = 50;
                    defaultY += 400; // row height rough estimate
                }
            }

            // Create HTML element for table
            const card = document.createElement('div');
            card.id = `table-${tableName}`;
            card.className = `table-card absolute bg-white border border-slate-300 rounded-lg shadow-sm flex flex-col overflow-hidden w-64`;
            card.style.transform = `translate(${posX}px, ${posY}px)`;
            card.dataset.tableName = tableName;

            // Header (Draggable)
            const header = document.createElement('div');
            header.className = `table-header bg-slate-100 border-b border-slate-300 px-3 py-2 font-bold text-slate-800 text-sm cursor-move select-none flex justify-between items-center`;
            header.innerHTML = `<span>${tableName}</span><span class="text-xs font-normal text-slate-500">${columns.length} cols</span>`;
            
            // Add drag listener to header
            header.addEventListener('mousedown', (e) => {
                isDraggingNode = card;
                dragStartX = e.clientX - positions[tableName].x * scale;
                dragStartY = e.clientY - positions[tableName].y * scale;
                card.classList.add('dragging');
                e.stopPropagation(); // prevent panning
            });

            const body = document.createElement('div');
            body.className = `flex flex-col text-xs`;

            columns.forEach(col => {
                const isPk = col.Key === 'PRI';
                const row = document.createElement('div');
                row.id = `row-${tableName}-${col.Field}`;
                row.className = `table-row px-3 py-1.5 border-b border-slate-100 flex justify-between items-center last:border-0 hover:bg-slate-50 relative`;
                
                let icon = isPk ? `<span class="mr-1" title="Primary Key">🔑</span>` : `<span class="mr-1 w-4 inline-block"></span>`;
                
                row.innerHTML = `
                    <div class="flex items-center font-medium col-name ${isPk ? 'text-indigo-700' : 'text-slate-700'}">
                        ${icon}${col.Field}
                    </div>
                    <div class="text-slate-400 font-mono text-[10px] col-type">${col.Type}</div>
                `;
                body.appendChild(row);

                // Detect Relationships
                const targetTable = guessTargetTable(col.Field, tableNames);
                if (targetTable && targetTable !== tableName) { // avoid self links for now
                    relationships.push({
                        fromTable: tableName,
                        fromCol: col.Field,
                        toTable: targetTable,
                        toCol: 'id' // assuming standard PK
                    });
                }
            });

            card.appendChild(header);
            card.appendChild(body);
            plane.appendChild(card);
        });

        // Save initial layout if we just generated it
        localStorage.setItem('nibash_schema_positions', JSON.stringify(positions));

        // After placing all tables, wait for DOM to render then draw lines
        setTimeout(drawLines, 100);
    }

    // Draw SVG Lines
    function drawLines() {
        // SVG layer should be same size as bounding box or just use overflow:visible
        svgLayer.innerHTML = ''; // clear

        relationships.forEach(rel => {
            const fromRow = document.getElementById(`row-${rel.fromTable}-${rel.fromCol}`);
            // To find the PK row of target table, usually it's 'id'.
            // If we don't know the exact PK name, we can just point to the table header.
            let toRow = document.getElementById(`row-${rel.toTable}-id`);
            if (!toRow) {
                // fallback to table header if 'id' is not the PK
                toRow = document.getElementById(`table-${rel.toTable}`).firstElementChild;
            }

            if (!fromRow || !toRow) return;

            // Get coordinates relative to plane
            const fromRect = fromRow.getBoundingClientRect();
            const toRect = toRow.getBoundingClientRect();
            const planeRect = plane.getBoundingClientRect();

            // Calculate center points of the rows relative to the unscaled plane
            // bounding client rect includes scale and pan, so we must calculate reverse
            
            // Simpler approach: Just use positions object and known heights.
            // But DOM might change. 
            // Better: get relative offset within the card.
            const fromCard = document.getElementById(`table-${rel.fromTable}`);
            const toCard = document.getElementById(`table-${rel.toTable}`);

            const fromCardX = positions[rel.fromTable].x;
            const fromCardY = positions[rel.fromTable].y;
            const toCardX = positions[rel.toTable].x;
            const toCardY = positions[rel.toTable].y;

            // Row Y offset relative to card
            const fromOffsetY = fromRow.offsetTop + (fromRow.offsetHeight / 2);
            const toOffsetY = toRow.offsetTop + (toRow.offsetHeight / 2);

            // Connect from right side of fromCard if target is to the right, else left side
            const fromX = fromCardX < toCardX ? fromCardX + fromCard.offsetWidth : fromCardX;
            const fromY = fromCardY + fromOffsetY;

            const toX = fromCardX < toCardX ? toCardX : toCardX + toCard.offsetWidth;
            const toY = toCardY + toOffsetY;

            // Draw Bezier curve
            const controlOffset = Math.abs(toX - fromX) * 0.5 + 50;
            const c1X = fromCardX < toCardX ? fromX + controlOffset : fromX - controlOffset;
            const c2X = fromCardX < toCardX ? toX - controlOffset : toX + controlOffset;

            const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
            path.setAttribute('d', `M ${fromX} ${fromY} C ${c1X} ${fromY}, ${c2X} ${toY}, ${toX} ${toY}`);
            path.setAttribute('fill', 'transparent');
            path.setAttribute('stroke', '#cbd5e1'); // slate-300
            path.setAttribute('stroke-width', '1.5');
            path.setAttribute('class', 'svg-line');
            
            // Add a small circle at the ends
            const circleEnd = document.createElementNS("http://www.w3.org/2000/svg", "circle");
            circleEnd.setAttribute('cx', toX);
            circleEnd.setAttribute('cy', toY);
            circleEnd.setAttribute('r', '3');
            circleEnd.setAttribute('fill', '#94a3b8');

            svgLayer.appendChild(path);
            svgLayer.appendChild(circleEnd);
        });
    }

    // Apply Transforms
    function updateTransform() {
        plane.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
    }

    // --- Interaction Events ---

    // Panning
    canvasContainer.addEventListener('mousedown', (e) => {
        if (!isDraggingNode) {
            isPanning = true;
            startPanX = e.clientX - panX;
            startPanY = e.clientY - panY;
            canvasContainer.style.cursor = 'grabbing';
        }
    });

    window.addEventListener('mousemove', (e) => {
        if (isPanning) {
            panX = e.clientX - startPanX;
            panY = e.clientY - startPanY;
            updateTransform();
        } else if (isDraggingNode) {
            const tableName = isDraggingNode.dataset.tableName;
            // Calculate new X/Y taking scale into account
            const newX = (e.clientX - dragStartX) / scale;
            const newY = (e.clientY - dragStartY) / scale;
            
            positions[tableName].x = newX;
            positions[tableName].y = newY;
            
            isDraggingNode.style.transform = `translate(${newX}px, ${newY}px)`;
            drawLines(); // redraw lines in real-time
        }
    });

    window.addEventListener('mouseup', () => {
        if (isPanning) {
            isPanning = false;
            canvasContainer.style.cursor = 'default';
        }
        if (isDraggingNode) {
            isDraggingNode.classList.remove('dragging');
            isDraggingNode = null;
            // Save positions
            localStorage.setItem('nibash_schema_positions', JSON.stringify(positions));
        }
    });

    // Zooming
    canvasContainer.addEventListener('wheel', (e) => {
        e.preventDefault();
        const zoomIntensity = 0.1;
        const delta = e.deltaY > 0 ? -1 : 1;
        
        // Calculate point under mouse to zoom towards it
        const rect = canvasContainer.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;

        // Position before zoom relative to unscaled plane
        const pointX = (mouseX - panX) / scale;
        const pointY = (mouseY - panY) / scale;

        let newScale = scale * Math.exp(delta * zoomIntensity);
        newScale = Math.min(Math.max(0.1, newScale), 3); // clamp scale

        // Adjust pan to keep mouse over same point
        panX = mouseX - pointX * newScale;
        panY = mouseY - pointY * newScale;
        scale = newScale;

        updateTransform();
    }, { passive: false });

    // --- Buttons ---

    // Reset
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            localStorage.removeItem('nibash_schema_positions');
            window.location.reload();
        });
    }

    // Toggle B&W
    if (toggleColorBtn) {
        let isBw = false;
        toggleColorBtn.addEventListener('click', () => {
            isBw = !isBw;
            if (isBw) {
                document.body.classList.add('bw-mode');
            } else {
                document.body.classList.remove('bw-mode');
            }
        });
    }

    // Print
    if (printBtn) {
        printBtn.addEventListener('click', () => {
            // Force pure white backgrounds
            document.body.classList.add('bw-mode');
            
            // Temporarily reset pan/scale to fit printed page or at least show origin
            const oldPanX = panX, oldPanY = panY, oldScale = scale;
            panX = 0; panY = 0; scale = 0.5; // roughly scale down to fit some
            updateTransform();

            setTimeout(() => {
                window.print();
                // Restore after print
                document.body.classList.remove('bw-mode');
                panX = oldPanX; panY = oldPanY; scale = oldScale;
                updateTransform();
            }, 500);
        });
    }

    // Boot
    initSchema();
    updateTransform();
});
