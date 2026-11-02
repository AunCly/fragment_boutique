/**
 * Grid helpers: a grid is an array of rows of essence ids.
 */

export const cloneGrid = (grid) => grid.map((row) => [...row]);

export const gridsEqual = (a, b) => JSON.stringify(a) === JSON.stringify(b);

export function counts(grid) {
    const result = new Map();
    for (const row of grid) {
        for (const id of row) {
            result.set(id, (result.get(id) ?? 0) + 1);
        }
    }
    return result;
}

/**
 * Applies the manual edits (Map "row:col" => id) on top of a grid.
 */
export function withEdits(grid, edits) {
    const result = cloneGrid(grid);
    for (const [key, id] of edits) {
        const [row, col] = key.split(':').map(Number);
        if (result[row]?.[col] !== undefined) {
            result[row][col] = id;
        }
    }
    return result;
}

/**
 * Cells of the 4-connected area of the same essence as (row, col).
 */
export function floodArea(grid, row, col) {
    const target = grid[row][col];
    const seen = new Set([`${row}:${col}`]);
    const stack = [[row, col]];
    const cells = [];

    while (stack.length) {
        const [r, c] = stack.pop();
        cells.push([r, c]);
        for (const [dr, dc] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
            const nr = r + dr;
            const nc = c + dc;
            const key = `${nr}:${nc}`;
            if (grid[nr]?.[nc] === target && !seen.has(key)) {
                seen.add(key);
                stack.push([nr, nc]);
            }
        }
    }

    return cells;
}

export const markerEvery = (cols, rows) => (Math.max(cols, rows) <= 16 ? 4 : 8);

/**
 * Draws a grid in flat colours on a canvas.
 */
export function drawGrid(canvas, grid, essences, cellPx, { lines = false, markers = false } = {}) {
    const rows = grid.length;
    const cols = grid[0].length;
    canvas.width = cols * cellPx;
    canvas.height = rows * cellPx;
    const ctx = canvas.getContext('2d');

    for (let r = 0; r < rows; r++) {
        for (let c = 0; c < cols; c++) {
            ctx.fillStyle = essences.get(grid[r][c])?.hex ?? '#ff00ff';
            ctx.fillRect(c * cellPx, r * cellPx, cellPx, cellPx);
        }
    }

    if (lines && cellPx >= 6) {
        ctx.strokeStyle = 'rgba(0, 0, 0, 0.18)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        for (let c = 1; c < cols; c++) {
            ctx.moveTo(c * cellPx + 0.5, 0);
            ctx.lineTo(c * cellPx + 0.5, rows * cellPx);
        }
        for (let r = 1; r < rows; r++) {
            ctx.moveTo(0, r * cellPx + 0.5);
            ctx.lineTo(cols * cellPx, r * cellPx + 0.5);
        }
        ctx.stroke();
    }

    if (markers) {
        const every = markerEvery(cols, rows);
        ctx.strokeStyle = 'rgba(0, 0, 0, 0.55)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        for (let c = every; c < cols; c += every) {
            ctx.moveTo(c * cellPx, 0);
            ctx.lineTo(c * cellPx, rows * cellPx);
        }
        for (let r = every; r < rows; r += every) {
            ctx.moveTo(0, r * cellPx);
            ctx.lineTo(cols * cellPx, r * cellPx);
        }
        ctx.stroke();
    }

    return ctx;
}
