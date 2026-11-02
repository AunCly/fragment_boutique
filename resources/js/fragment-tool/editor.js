import { drawGrid, floodArea, withEdits } from './grid.js';

const HISTORY_LIMIT = 200;

/**
 * Cell-by-cell editor. Manual edits are kept on top of the base grid (version + motifs),
 * so changing the motif keeps them.
 */
export class PixelEditor {
    constructor(canvas, { onChange, onHover, onPick }) {
        this.canvas = canvas;
        this.onChange = onChange;
        this.onHover = onHover;
        this.onPick = onPick;
        this.base = null;
        this.essences = new Map();
        this.edits = new Map();
        this.undoStack = [];
        this.redoStack = [];
        this.tool = 'brush';
        this.essenceId = null;
        this.showGrid = true;
        this.highlight = null;
        this.stroke = null;
        this.cellPx = 16;

        canvas.addEventListener('pointerdown', (event) => this.pointerDown(event));
        canvas.addEventListener('pointermove', (event) => this.pointerMove(event));
        canvas.addEventListener('pointerup', (event) => this.pointerUp(event));
        canvas.addEventListener('pointerleave', () => this.onHover(null));
        window.addEventListener('resize', () => this.render());
    }

    setEssences(essences) {
        this.essences = essences;
        this.render();
    }

    setBase(grid) {
        this.base = grid;
        this.render();
    }

    hasEdits() {
        return this.edits.size > 0;
    }

    /** Drops edits and history (new version). */
    reset() {
        this.edits = new Map();
        this.undoStack = [];
        this.redoStack = [];
        this.highlight = null;
    }

    current() {
        return this.base ? withEdits(this.base, this.edits) : null;
    }

    /**
     * Applies changes [[row, col, id], …] as one undoable step.
     */
    apply(changes) {
        const entry = new Map();
        for (const [row, col, id] of changes) {
            const key = `${row}:${col}`;
            if (!entry.has(key)) {
                entry.set(key, { before: this.edits.get(key), after: id });
            } else {
                entry.get(key).after = id;
            }
            this.edits.set(key, id);
        }
        this.commit(entry);
    }

    clearEdits() {
        if (!this.edits.size) {
            return;
        }
        const entry = new Map();
        for (const [key, id] of this.edits) {
            entry.set(key, { before: id, after: undefined });
        }
        this.edits = new Map();
        this.commit(entry);
    }

    undo() {
        const entry = this.undoStack.pop();
        if (!entry) {
            return;
        }
        this.restore(entry, 'before');
        this.redoStack.push(entry);
        this.changed();
    }

    redo() {
        const entry = this.redoStack.pop();
        if (!entry) {
            return;
        }
        this.restore(entry, 'after');
        this.undoStack.push(entry);
        this.changed();
    }

    restore(entry, side) {
        for (const [key, values] of entry) {
            if (values[side] === undefined) {
                this.edits.delete(key);
            } else {
                this.edits.set(key, values[side]);
            }
        }
    }

    commit(entry) {
        if (!entry.size) {
            return;
        }
        this.undoStack.push(entry);
        if (this.undoStack.length > HISTORY_LIMIT) {
            this.undoStack.shift();
        }
        this.redoStack = [];
        this.changed();
    }

    changed() {
        this.render();
        this.onChange(this.current());
    }

    setHighlight(cell) {
        this.highlight = cell;
        this.render();
    }

    render() {
        const grid = this.current();
        if (!grid) {
            return;
        }
        const rows = grid.length;
        const cols = grid[0].length;
        const available = this.canvas.parentElement.clientWidth || 640;
        this.cellPx = Math.max(6, Math.min(28, Math.floor(available / cols), Math.floor(640 / rows)));

        const ctx = drawGrid(this.canvas, grid, this.essences, this.cellPx, { lines: this.showGrid, markers: this.showGrid });

        if (this.highlight) {
            ctx.strokeStyle = '#e11d48';
            ctx.lineWidth = 3;
            ctx.strokeRect(this.highlight.col * this.cellPx + 1.5, this.highlight.row * this.cellPx + 1.5, this.cellPx - 3, this.cellPx - 3);
        }
    }

    cellAt(event) {
        const rect = this.canvas.getBoundingClientRect();
        const col = Math.floor(((event.clientX - rect.left) * (this.canvas.width / rect.width)) / this.cellPx);
        const row = Math.floor(((event.clientY - rect.top) * (this.canvas.height / rect.height)) / this.cellPx);
        const grid = this.current();
        return grid?.[row]?.[col] !== undefined ? { row, col } : null;
    }

    pointerDown(event) {
        const cell = this.cellAt(event);
        if (!cell) {
            return;
        }
        const grid = this.current();

        if (this.tool === 'picker' || event.altKey) {
            this.onPick(grid[cell.row][cell.col]);
            return;
        }
        if (this.essenceId === null) {
            return;
        }
        if (this.tool === 'fill') {
            this.apply(floodArea(grid, cell.row, cell.col).map(([row, col]) => [row, col, this.essenceId]));
            return;
        }

        this.stroke = new Map();
        this.canvas.setPointerCapture(event.pointerId);
        this.paint(cell);
    }

    pointerMove(event) {
        const cell = this.cellAt(event);
        this.onHover(cell ? { ...cell, id: this.current()[cell.row][cell.col] } : null);
        if (this.stroke && cell) {
            this.paint(cell);
        }
    }

    pointerUp(event) {
        if (!this.stroke) {
            return;
        }
        if (this.canvas.hasPointerCapture(event.pointerId)) {
            this.canvas.releasePointerCapture(event.pointerId);
        }
        const entry = this.stroke;
        this.stroke = null;
        this.commit(entry);
    }

    paint({ row, col }) {
        const key = `${row}:${col}`;
        if (this.current()[row][col] === this.essenceId && !this.stroke.has(key)) {
            return;
        }
        if (!this.stroke.has(key)) {
            this.stroke.set(key, { before: this.edits.get(key), after: this.essenceId });
        }
        this.edits.set(key, this.essenceId);
        this.render();
    }
}
