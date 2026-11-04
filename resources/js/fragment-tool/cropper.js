const HANDLE_PX = 14;
const MAX_HEIGHT = 520;

/**
 * Crop frame on a canvas, locked to the format ratio: move by dragging inside,
 * resize by the corners. The crop is kept in source image pixels.
 */
export class Cropper {
    constructor(canvas, { onChange }) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.onChange = onChange;
        this.image = null;
        this.cols = 1;
        this.rows = 1;
        this.crop = null;
        this.drag = null;
        this.locked = false;

        canvas.addEventListener('pointerdown', (event) => this.start(event));
        canvas.addEventListener('pointermove', (event) => this.move(event));
        canvas.addEventListener('pointerup', (event) => this.end(event));
        canvas.addEventListener('pointercancel', (event) => this.end(event));
        window.addEventListener('resize', () => this.layout());
    }

    /** New image, with the given crop or the default one. */
    setImage(image, crop = null) {
        this.image = image;
        if (!crop) {
            this.reset();
            return;
        }
        this.crop = { ...crop };
        this.layout();
        this.onChange(this.crop);
    }

    setFormat(cols, rows) {
        this.cols = cols;
        this.rows = rows;
        if (this.image) {
            this.reset();
        }
    }

    /** Largest centred crop with the format ratio. */
    reset() {
        const { naturalWidth: width, naturalHeight: height } = this.image;
        const scale = Math.min(width / this.cols, height / this.rows);
        const w = Math.floor(this.cols * scale);
        const h = Math.floor(this.rows * scale);
        this.crop = { x: Math.floor((width - w) / 2), y: Math.floor((height - h) / 2), width: w, height: h };
        this.layout();
        this.onChange(this.crop);
    }

    isLargeEnough() {
        return this.crop && this.crop.width >= this.cols && this.crop.height >= this.rows;
    }

    layout() {
        if (!this.image) {
            return;
        }
        const available = this.canvas.parentElement.parentElement.clientWidth || 720;
        this.scale = Math.min(available / this.image.naturalWidth, MAX_HEIGHT / this.image.naturalHeight, 1);
        this.canvas.width = Math.round(this.image.naturalWidth * this.scale);
        this.canvas.height = Math.round(this.image.naturalHeight * this.scale);
        this.draw();
    }

    draw() {
        const { ctx, canvas, scale } = this;
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        const tile = 10;
        for (let y = 0; y < canvas.height; y += tile) {
            for (let x = 0; x < canvas.width; x += tile) {
                ctx.fillStyle = (x / tile + y / tile) % 2 === 0 ? '#eee' : '#ccc';
                ctx.fillRect(x, y, tile, tile);
            }
        }
        ctx.drawImage(this.image, 0, 0, canvas.width, canvas.height);

        const x = this.crop.x * scale;
        const y = this.crop.y * scale;
        const w = this.crop.width * scale;
        const h = this.crop.height * scale;

        ctx.fillStyle = 'rgba(22, 19, 20, 0.55)';
        ctx.fillRect(0, 0, canvas.width, y);
        ctx.fillRect(0, y + h, canvas.width, canvas.height - y - h);
        ctx.fillRect(0, y, x, h);
        ctx.fillRect(x + w, y, canvas.width - x - w, h);

        const cell = w / this.cols;
        if (cell >= 4) {
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.35)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            for (let c = 1; c < this.cols; c++) {
                ctx.moveTo(x + c * cell, y);
                ctx.lineTo(x + c * cell, y + h);
            }
            for (let r = 1; r < this.rows; r++) {
                ctx.moveTo(x, y + r * (h / this.rows));
                ctx.lineTo(x + w, y + r * (h / this.rows));
            }
            ctx.stroke();
        }

        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        ctx.strokeRect(x, y, w, h);
        ctx.fillStyle = '#fff';
        for (const [cx, cy] of this.corners()) {
            ctx.fillRect(cx - 5, cy - 5, 10, 10);
        }
    }

    corners() {
        const { x, y, width, height } = this.crop;
        const s = this.scale;
        return [
            [x * s, y * s],
            [(x + width) * s, y * s],
            [x * s, (y + height) * s],
            [(x + width) * s, (y + height) * s],
        ];
    }

    pointer(event) {
        const rect = this.canvas.getBoundingClientRect();
        return {
            x: ((event.clientX - rect.left) * (this.canvas.width / rect.width)),
            y: ((event.clientY - rect.top) * (this.canvas.height / rect.height)),
        };
    }

    start(event) {
        if (!this.crop || this.locked) {
            return;
        }
        const point = this.pointer(event);
        const corner = this.corners().findIndex(([cx, cy]) => Math.abs(cx - point.x) <= HANDLE_PX && Math.abs(cy - point.y) <= HANDLE_PX);
        const { x, y, width, height } = this.crop;

        if (corner >= 0) {
            // Anchor = the opposite corner, in source pixels.
            const anchorX = corner % 2 === 0 ? x + width : x;
            const anchorY = corner < 2 ? y + height : y;
            this.drag = { mode: 'resize', anchorX, anchorY };
        } else if (point.x >= x * this.scale && point.x <= (x + width) * this.scale && point.y >= y * this.scale && point.y <= (y + height) * this.scale) {
            this.drag = { mode: 'move', offsetX: point.x / this.scale - x, offsetY: point.y / this.scale - y };
        } else {
            return;
        }
        this.canvas.setPointerCapture(event.pointerId);
    }

    move(event) {
        const point = this.pointer(event);
        if (!this.drag) {
            if (this.locked) {
                this.canvas.style.cursor = 'not-allowed';
                return;
            }
            const overCorner = this.crop && this.corners().some(([cx, cy]) => Math.abs(cx - point.x) <= HANDLE_PX && Math.abs(cy - point.y) <= HANDLE_PX);
            this.canvas.style.cursor = overCorner ? 'nwse-resize' : 'move';
            return;
        }

        const { naturalWidth: imageWidth, naturalHeight: imageHeight } = this.image;
        const sx = point.x / this.scale;
        const sy = point.y / this.scale;

        if (this.drag.mode === 'move') {
            this.crop.x = Math.round(Math.min(Math.max(0, sx - this.drag.offsetX), imageWidth - this.crop.width));
            this.crop.y = Math.round(Math.min(Math.max(0, sy - this.drag.offsetY), imageHeight - this.crop.height));
        } else {
            const { anchorX, anchorY } = this.drag;
            const ratio = this.cols / this.rows;
            const dirX = sx < anchorX ? -1 : 1;
            const dirY = sy < anchorY ? -1 : 1;
            const maxWidth = dirX < 0 ? anchorX : imageWidth - anchorX;
            const maxHeight = dirY < 0 ? anchorY : imageHeight - anchorY;

            let width = Math.max(Math.abs(sx - anchorX), Math.abs(sy - anchorY) * ratio);
            width = Math.min(width, maxWidth, maxHeight * ratio);
            width = Math.max(width, this.cols);
            const height = width / ratio;

            this.crop.width = Math.round(width);
            this.crop.height = Math.round(height);
            this.crop.x = Math.round(dirX < 0 ? anchorX - width : anchorX);
            this.crop.y = Math.round(dirY < 0 ? anchorY - height : anchorY);
        }

        this.draw();
    }

    end(event) {
        if (!this.drag) {
            return;
        }
        this.drag = null;
        if (this.canvas.hasPointerCapture(event.pointerId)) {
            this.canvas.releasePointerCapture(event.pointerId);
        }
        this.onChange(this.crop);
    }
}
