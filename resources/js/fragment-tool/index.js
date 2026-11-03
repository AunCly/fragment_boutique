import { download, postBlob, postJson, postStream, ToolError } from './api.js';
import { Cropper } from './cropper.js';
import { PixelEditor } from './editor.js';
import { counts, drawGrid } from './grid.js';

const STYLE_LABELS = { base: 'Standard', contrast: 'Contrasté', faithful: 'Fidèle', cute: 'Mignon' };
const RENDER_DELAY_MS = 500;

const formatDuration = (ms) => {
    const seconds = Math.floor(ms / 1000);
    return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
};

class FragmentTool {
    constructor(root) {
        this.root = root;
        this.config = JSON.parse(this.ref('config').textContent);
        this.file = null;
        this.imageName = 'fragment';
        this.hasTransparency = false;
        this.crop = null;
        this.format = null;
        this.palette = null;
        this.essences = new Map();
        this.versions = [];
        this.selectedId = null;
        this.nextVersionId = 1;
        this.suggestions = [];
        this.cost = 0;
        this.aiController = null;
        this.aiTimer = null;
        this.aiStartedAt = null;
        this.aiTotalMs = 0;
        this.motifController = null;
        this.renderController = null;
        this.renderTimer = null;
        this.objectUrls = {};

        this.cropper = new Cropper(this.ref('crop-canvas'), { onChange: (crop) => this.cropChanged(crop) });
        this.editor = new PixelEditor(this.ref('editor'), {
            onChange: () => this.scheduleRender(),
            onHover: (cell) => this.showHover(cell),
            onPick: (id) => this.selectEssence(id),
        });

        this.bind();
        this.selectPalette(this.config.palettes[0].id);
        this.selectFormat(Number(this.root.querySelector('[data-format]').dataset.format));
        this.selectTool('brush');
        this.selectAiTab('refine');
        this.selectResultTab('wood');
        this.updateButtons();
    }

    ref(name) {
        return this.root.querySelector(`[data-ref="${name}"]`);
    }

    action(name) {
        return this.root.querySelector(`[data-action="${name}"]`);
    }

    bind() {
        const fileInput = this.ref('file');
        const dropzone = this.ref('dropzone');

        fileInput.addEventListener('change', () => fileInput.files[0] && this.loadFile(fileInput.files[0]));
        dropzone.addEventListener('dragover', (event) => {
            event.preventDefault();
            dropzone.classList.add('bg-muted/40');
        });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('bg-muted/40'));
        dropzone.addEventListener('drop', (event) => {
            event.preventDefault();
            dropzone.classList.remove('bg-muted/40');
            const file = event.dataTransfer.files[0];
            if (file) {
                this.loadFile(file);
            }
        });

        this.action('crop-reset').addEventListener('click', () => this.cropper.reset());
        this.action('change-image').addEventListener('click', () => fileInput.click());

        this.ref('palette').addEventListener('change', (event) => {
            if (!this.confirmReset('Changer de palette efface les versions. Continuer ?')) {
                event.target.value = this.palette.id;
                return;
            }
            this.selectPalette(Number(event.target.value));
        });

        this.root.querySelectorAll('[data-format]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = Number(button.dataset.format);
                if (id === this.format?.id || !this.confirmReset('Changer de format efface les versions. Continuer ?')) {
                    return;
                }
                this.selectFormat(id);
            });
        });

        this.action('generate').addEventListener('click', () => this.generate());
        this.action('stop').addEventListener('click', () => this.aiController?.abort());
        this.action('clear-versions').addEventListener('click', () => {
            if (window.confirm('Effacer toutes les versions ?')) {
                this.clearVersions();
            }
        });
        this.action('refine').addEventListener('click', () => this.refine());
        this.ref('test-mode').addEventListener('change', () => {
            this.ref('stats-mode').classList.toggle('hidden', !this.isTestMode());
            this.ref('ai-help').classList.toggle('hidden', !this.selectedVersion() || !this.isAiAvailable());
            this.updateButtons();
        });
        this.action('new-project').addEventListener('click', () => {
            if (this.confirmReset('Recommencer efface l\'image et les versions. Continuer ?')) {
                window.location.reload();
            }
        });
        this.root.querySelectorAll('[data-ai-tab]').forEach((button) => {
            button.addEventListener('click', () => this.selectAiTab(button.dataset.aiTab));
        });
        this.root.querySelectorAll('[data-result-tab]').forEach((button) => {
            button.addEventListener('click', () => this.selectResultTab(button.dataset.resultTab));
        });

        this.root.querySelectorAll('[data-tool]').forEach((button) => {
            button.addEventListener('click', () => this.selectTool(button.dataset.tool));
        });
        this.action('undo').addEventListener('click', () => this.editor.undo());
        this.action('redo').addEventListener('click', () => this.editor.redo());
        this.action('clear-edits').addEventListener('click', () => this.editor.clearEdits());
        this.ref('show-grid').addEventListener('change', (event) => {
            this.editor.showGrid = event.target.checked;
            this.editor.render();
        });

        for (const name of ['motif', 'density', 'motif-essence']) {
            this.ref(name).addEventListener('change', () => this.applyMotif());
        }

        this.action('suggest').addEventListener('click', () => this.suggest());
        this.action('apply-suggestions').addEventListener('click', () => this.applySuggestions());

        this.root.querySelectorAll('[data-download]').forEach((button) => {
            button.addEventListener('click', () => this.downloadRender(button.dataset.download));
        });
        this.action('download-zip').addEventListener('click', () => this.downloadArchive());

        document.addEventListener('keydown', (event) => this.shortcut(event));
    }

    /* ---------------------------------------------------------------- Setup */

    confirmReset(message) {
        return this.versions.length === 0 || window.confirm(message);
    }

    selectPalette(id) {
        this.palette = this.config.palettes.find((palette) => palette.id === id);
        this.essences = new Map(this.palette.essences.map((essence) => [essence.id, essence]));
        this.ref('palette').value = id;
        this.clearVersions();

        this.ref('palette-swatches').replaceChildren(...this.palette.essences.map((essence) => {
            const swatch = document.createElement('span');
            swatch.className = 'w-5 h-5 rounded border border-border';
            swatch.style.backgroundColor = essence.hex;
            swatch.title = `${essence.code} · ${essence.name}`;
            return swatch;
        }));

        const options = (selectedId) => this.palette.essences.map((essence) => {
            const option = new Option(`${essence.code} · ${essence.name}`, essence.id);
            option.selected = essence.id === selectedId;
            return option;
        });
        this.ref('background').replaceChildren(...options(this.palette.defaultBackgroundId));
        this.ref('motif-essence').replaceChildren(...options(this.palette.defaultMotifId));

        this.editor.setEssences(this.essences);
        this.renderEditorPalette();
        this.selectEssence(this.palette.essences[0].id);
    }

    selectFormat(id) {
        const button = this.root.querySelector(`[data-format="${id}"]`);
        this.format = { id, cols: Number(button.dataset.cols), rows: Number(button.dataset.rows) };
        this.root.querySelectorAll('[data-format]').forEach((other) => {
            other.dataset.active = String(other === button);
        });
        this.clearVersions();
        this.cropper.setFormat(this.format.cols, this.format.rows);
    }

    loadFile(file) {
        if (!['image/png', 'image/jpeg'].includes(file.type)) {
            this.showError('Format non supporté : importez une image PNG ou JPG.');
            return;
        }
        if (file.size > this.config.maxUploadKb * 1024) {
            this.showError('Image trop lourde.');
            return;
        }
        if (this.file && !this.confirmReset('Changer d\'image efface les versions. Continuer ?')) {
            return;
        }

        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            this.file = file;
            this.imageName = file.name.replace(/\.[^.]+$/, '') || 'fragment';
            this.hasTransparency = file.type === 'image/png' && this.detectTransparency(image);
            this.ref('background-field').classList.toggle('hidden', !this.hasTransparency);
            this.ref('dropzone').classList.add('hidden');
            this.ref('cropper').classList.remove('hidden');
            this.clearVersions();
            this.cropper.setImage(image);
            this.hideError();
        };
        image.onerror = () => this.showError('Image illisible ou corrompue.');
        image.src = url;
    }

    detectTransparency(image) {
        const scale = Math.min(1, 512 / Math.max(image.naturalWidth, image.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
        const ctx = canvas.getContext('2d');
        ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
        const { data } = ctx.getImageData(0, 0, canvas.width, canvas.height);
        for (let i = 3; i < data.length; i += 4) {
            if (data[i] < 255) {
                return true;
            }
        }
        return false;
    }

    cropChanged(crop) {
        this.crop = { ...crop };
        const info = this.ref('crop-info');
        if (!this.cropper.isLargeEnough()) {
            info.textContent = `Zone trop petite : au moins ${this.format.cols} × ${this.format.rows} pixels.`;
        } else {
            info.textContent = `Zone : ${crop.width} × ${crop.height} px · ${(crop.width / this.format.cols).toFixed(1)} px par case`;
        }
        this.updateButtons();
    }

    backgroundId() {
        return this.hasTransparency ? Number(this.ref('background').value) : this.palette.defaultBackgroundId;
    }

    /* ---------------------------------------------------------------- Requests */

    formData({ withImage = false, backgroundId = this.backgroundId() } = {}) {
        const data = new FormData();
        data.append('format_id', this.format.id);
        data.append('palette_id', this.palette.id);
        data.append('background_id', backgroundId);
        if (withImage) {
            data.append('image', this.file);
            data.append('crop_x', this.crop.x);
            data.append('crop_y', this.crop.y);
            data.append('crop_width', this.crop.width);
            data.append('crop_height', this.crop.height);
        }
        return data;
    }

    /**
     * AI request data; test mode makes the server answer without calling Gemini.
     */
    aiData(options) {
        const data = this.formData({ withImage: true, ...options });
        data.append('test_mode', this.isTestMode() ? '1' : '0');
        return data;
    }

    isTestMode() {
        return this.ref('test-mode').checked;
    }

    isAiAvailable() {
        return this.config.ai.enabled || this.isTestMode();
    }

    /**
     * Header progress: done steps, then the current one.
     */
    updateSteps() {
        const current = this.selectedVersion() ? 3 : this.versions.length ? 2 : this.file ? 1 : 0;
        this.root.querySelectorAll('[data-step]').forEach((step) => {
            const index = Number(step.dataset.step);
            step.dataset.state = index < current ? 'done' : index === current ? 'current' : 'todo';
        });
    }

    selectAiTab(name) {
        this.root.querySelectorAll('[data-ai-tab]').forEach((button) => {
            button.dataset.active = String(button.dataset.aiTab === name);
        });
        this.root.querySelectorAll('[data-ai-panel]').forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.aiPanel !== name);
        });
    }

    selectResultTab(name) {
        this.root.querySelectorAll('[data-result-tab]').forEach((button) => {
            button.dataset.active = String(button.dataset.resultTab === name);
        });
        this.root.querySelectorAll('[data-result-panel]').forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.resultPanel !== name);
        });
    }

    canSendImage() {
        return Boolean(this.file && this.crop && this.cropper.isLargeEnough());
    }

    updateButtons() {
        this.updateSteps();
        const busy = Boolean(this.aiController);
        const ready = this.canSendImage();
        const selected = this.selectedVersion();

        const ai = this.isAiAvailable();

        this.action('generate').disabled = busy || !ready || !ai;
        this.action('refine').disabled = busy || !ready || !selected || !ai;
        this.action('suggest').disabled = busy || !ready || !selected || !ai;
    }

    startAi(message) {
        this.aiController = new AbortController();
        this.hideError();
        this.ref('warnings').replaceChildren();
        this.setStatus(message);
        this.updateButtons();
        this.showAiProgress();
        return this.aiController.signal;
    }

    endAi() {
        this.aiController = null;
        this.hideAiProgress();
        this.updateButtons();
    }

    showAiProgress() {
        this.aiStartedAt = Date.now();
        const tick = () => {
            const elapsed = formatDuration(Date.now() - this.aiStartedAt);
            this.ref('ai-progress-timer').textContent = elapsed;
            this.ref('stats-last').textContent = elapsed;
        };

        tick();
        this.aiTimer = window.setInterval(tick, 1000);
        this.ref('ai-progress').classList.remove('hidden');
    }

    hideAiProgress() {
        window.clearInterval(this.aiTimer);
        this.aiTimer = null;

        const elapsed = Date.now() - this.aiStartedAt;
        this.aiTotalMs += elapsed;
        this.ref('stats-last').textContent = formatDuration(elapsed);
        this.ref('stats-total').textContent = formatDuration(this.aiTotalMs);

        this.ref('ai-progress').classList.add('hidden');
    }

    handleFailure(error) {
        if (error.name === 'AbortError') {
            this.setStatus('Arrêté. Les appels déjà partis chez Google peuvent être facturés.');
            return;
        }
        this.setStatus('');
        this.showError(error instanceof ToolError ? error.message : `Erreur inattendue : ${error.message}`);
    }

    addCost(usage) {
        if (!usage) {
            return;
        }
        this.cost += usage.cost_usd ?? 0;
        this.ref('stats-cost').textContent = `${this.cost.toFixed(4)} $`;

        const models = [...new Set((usage.calls ?? []).map((call) => call.model))];
        if (models.length) {
            this.ref('stats-models').textContent = models.join(', ');
        }
    }

    async generate() {
        const count = Number(this.root.querySelector('input[name="ai-count"]:checked').value);
        const data = this.aiData();
        data.append('count', count);
        data.append('hint', this.ref('hint').value.trim());

        const signal = this.startAi('Envoi…');
        const batch = new Map();
        const backgroundId = this.backgroundId();

        try {
            await postStream(this.config.routes.generate, data, (event) => {
                if (event.type === 'stage') {
                    this.setStatus(event.message);
                } else if (event.type === 'warning') {
                    this.addWarning(event.message);
                } else if (event.type === 'candidate') {
                    batch.set(event.index, this.addVersion({
                        grid: event.grid,
                        label: `IA · ${STYLE_LABELS[event.style] ?? event.style}`,
                        kind: 'ai',
                        backgroundId,
                    }));
                } else if (event.type === 'result') {
                    for (const candidate of event.candidates) {
                        const version = batch.get(candidate.index) ?? this.addVersion({
                            grid: candidate.grid,
                            label: `IA · ${STYLE_LABELS[candidate.style] ?? candidate.style}`,
                            kind: 'ai',
                            backgroundId,
                        });
                        version.score = candidate.score;
                        version.problems = candidate.problems;
                        version.preferred = candidate.index === event.preferred;
                    }
                    this.addCost(event.usage);
                    this.setStatus(`${event.candidates.length} version(s) ajoutée(s).`);
                    this.renderVersions();
                } else if (event.type === 'error') {
                    this.addCost(event.usage);
                    this.showError(event.message);
                    this.setStatus('');
                }
            }, signal);
        } catch (error) {
            this.handleFailure(error);
        } finally {
            this.endAi();
        }
    }

    async refine() {
        const version = this.selectedVersion();
        const data = this.aiData({ backgroundId: version.backgroundId });
        data.append('grid', JSON.stringify(version.grid));
        data.append('remarks', this.ref('remarks').value);
        data.append('hint', this.ref('hint').value.trim());

        const signal = this.startAi('Raffinement…');

        try {
            await postStream(this.config.routes.refine, data, (event) => {
                if (event.type === 'stage') {
                    this.setStatus(event.message);
                } else if (event.type === 'warning') {
                    this.addWarning(event.message);
                } else if (event.type === 'result') {
                    this.addCost(event.usage);
                    if (event.unchanged) {
                        this.setStatus('La grille n\'a pas changé : aucune version ajoutée.');
                        return;
                    }
                    const refined = this.addVersion({
                        grid: event.grid,
                        label: `Raffinée (de n° ${version.id})`,
                        kind: 'ai',
                        backgroundId: version.backgroundId,
                    });
                    refined.score = event.score;
                    refined.problems = event.problems;
                    this.renderVersions();
                    this.setStatus('Version raffinée ajoutée.');
                } else if (event.type === 'error') {
                    this.addCost(event.usage);
                    this.showError(event.message);
                    this.setStatus('');
                }
            }, signal);
        } catch (error) {
            this.handleFailure(error);
        } finally {
            this.endAi();
        }
    }

    async suggest() {
        const version = this.selectedVersion();
        const data = this.aiData({ backgroundId: version.backgroundId });
        data.append('grid', JSON.stringify(this.editor.current()));

        const signal = this.startAi('Demande de suggestions…');

        try {
            const result = await postJson(this.config.routes.suggest, data, signal);
            this.addCost(result.usage);
            this.suggestions = result.suggestions;
            this.renderSuggestions();
            this.setStatus(result.suggestions.length ? `${result.suggestions.length} suggestion(s).` : 'Aucune suggestion.');
        } catch (error) {
            this.handleFailure(error);
        } finally {
            this.endAi();
        }
    }

    /* ---------------------------------------------------------------- Versions */

    addVersion({ grid, label, kind, backgroundId }) {
        const version = { id: this.nextVersionId++, grid, label, kind, backgroundId, score: null, problems: [], preferred: false };
        this.versions.push(version);
        this.renderVersions();
        return version;
    }

    selectedVersion() {
        return this.versions.find((version) => version.id === this.selectedId) ?? null;
    }

    selectVersion(id) {
        if (id === this.selectedId) {
            return;
        }
        if (this.editor.hasEdits() && !window.confirm('Changer de version efface les retouches. Continuer ?')) {
            return;
        }

        this.selectedId = id;
        const version = this.selectedVersion();
        this.editor.reset();
        this.suggestions = [];
        this.renderSuggestions();
        this.ref('remarks').value = version.problems.join('\n');
        this.ref('ai-help').classList.toggle('hidden', !this.isAiAvailable());
        for (const name of ['editor-section', 'finish-section', 'result-section']) {
            this.ref(name).classList.remove('hidden');
        }
        this.renderVersions();
        this.updateButtons();
        this.applyMotif();
    }

    clearVersions() {
        this.versions = [];
        this.selectedId = null;
        this.editor.reset();
        this.editor.base = null;
        this.suggestions = [];
        this.renderSuggestions();
        for (const name of ['versions-section', 'editor-section', 'finish-section', 'ai-help', 'result-section']) {
            this.ref(name).classList.add('hidden');
        }
        this.renderVersions();
        this.updateButtons();
    }

    renderVersions() {
        const container = this.ref('versions');
        this.ref('versions-section').classList.toggle('hidden', this.versions.length === 0);
        this.ref('empty-state').classList.toggle('hidden', this.versions.length > 0);
        this.ref('versions-count').textContent = `· ${this.versions.length}`;

        container.replaceChildren(...this.versions.map((version) => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = `relative shrink-0 w-28 p-1.5 rounded-lg border text-left transition ${version.id === this.selectedId ? 'border-foreground ring-1 ring-foreground' : 'border-border hover:bg-muted/50'}`;
            card.title = version.problems.length ? version.problems.map((problem) => `• ${problem}`).join('\n') : version.label;
            card.addEventListener('click', () => this.selectVersion(version.id));

            const canvas = document.createElement('canvas');
            canvas.className = 'w-full h-auto rounded [image-rendering:pixelated]';
            drawGrid(canvas, version.grid, this.essences, Math.max(1, Math.floor(128 / Math.max(version.grid.length, version.grid[0].length))));

            const header = document.createElement('div');
            header.className = 'flex items-center justify-between mt-1 font-sans-soft text-[11px]';
            const number = document.createElement('span');
            number.className = 'text-foreground';
            number.textContent = `n° ${version.id}`;
            const score = document.createElement('span');
            score.className = 'font-mono text-muted-foreground';
            score.textContent = version.score !== null ? `${version.score}/10` : '–';
            header.append(number, score);

            const caption = document.createElement('p');
            caption.className = 'font-sans-soft text-[10px] text-muted-foreground truncate';
            caption.textContent = version.problems.length ? `${version.label} · ${version.problems.length} problème(s)` : version.label;

            card.append(canvas, header, caption);

            if (version.preferred) {
                const star = document.createElement('span');
                star.className = 'absolute top-0.5 right-1.5 text-amber-500 text-sm';
                star.textContent = '★';
                star.title = 'Préférée du juge';
                card.append(star);
            }

            return card;
        }));
    }

    /* ---------------------------------------------------------------- Finishing */

    async applyMotif() {
        const version = this.selectedVersion();
        if (!version) {
            return;
        }

        this.motifController?.abort();
        const motif = this.ref('motif').value;

        if (motif === 'none') {
            this.editor.setBase(version.grid);
            this.scheduleRender();
            return;
        }

        this.motifController = new AbortController();
        const data = this.formData({ backgroundId: version.backgroundId });
        data.append('grid', JSON.stringify(version.grid));
        data.append('motif', motif);
        data.append('density', this.ref('density').value);
        data.append('essence_id', this.ref('motif-essence').value);

        try {
            const result = await postJson(this.config.routes.motifs, data, this.motifController.signal);
            if (this.selectedVersion() === version) {
                this.editor.setBase(result.grid);
                this.scheduleRender();
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.handleFailure(error);
            }
        }
    }

    renderEditorPalette() {
        this.ref('editor-palette').replaceChildren(...this.palette.essences.map((essence, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.essence = essence.id;
            button.className = 'flex items-center gap-2 px-1.5 py-1 rounded-lg font-sans-soft text-xs text-left hover:bg-muted/50 data-[active=true]:bg-foreground/10 data-[active=true]:ring-1 data-[active=true]:ring-foreground';
            button.title = `${essence.name}${index < 10 ? ` (touche ${(index + 1) % 10})` : ''}`;
            const swatch = document.createElement('span');
            swatch.className = 'w-4 h-4 rounded-full border border-border shrink-0';
            swatch.style.backgroundColor = essence.hex;
            const name = document.createElement('span');
            name.className = 'truncate';
            name.textContent = essence.name;
            const code = document.createElement('span');
            code.className = 'ml-auto font-mono text-[10px] text-muted-foreground';
            code.textContent = essence.code;
            button.append(swatch, name, code);
            button.addEventListener('click', () => this.selectEssence(essence.id));
            return button;
        }));
    }

    selectEssence(id) {
        this.editor.essenceId = id;
        this.ref('editor-palette').querySelectorAll('[data-essence]').forEach((button) => {
            button.dataset.active = String(Number(button.dataset.essence) === id);
        });
    }

    selectTool(tool) {
        this.editor.tool = tool;
        this.root.querySelectorAll('[data-tool]').forEach((button) => {
            button.dataset.active = String(button.dataset.tool === tool);
        });
    }

    showHover(cell) {
        const info = this.ref('hover-info');
        if (!cell) {
            info.textContent = '';
            return;
        }
        const essence = this.essences.get(cell.id);
        info.textContent = `Ligne ${cell.row + 1}, colonne ${cell.col + 1} · ${essence?.code} ${essence?.name}`;
    }

    shortcut(event) {
        if (!this.selectedVersion() || event.target.closest('input, textarea, select')) {
            return;
        }
        const key = event.key.toLowerCase();
        const mod = event.metaKey || event.ctrlKey;

        if (mod && key === 'z') {
            event.preventDefault();
            if (event.shiftKey) {
                this.editor.redo();
            } else {
                this.editor.undo();
            }
        } else if (mod && key === 'y') {
            event.preventDefault();
            this.editor.redo();
        } else if (!mod && key === 'b') {
            this.selectTool('brush');
        } else if (!mod && key === 'f') {
            this.selectTool('fill');
        } else if (!mod && key === 'i') {
            this.selectTool('picker');
        } else if (!mod && /^[0-9]$/.test(key)) {
            const essence = this.palette.essences[(Number(key) + 9) % 10];
            if (essence) {
                this.selectEssence(essence.id);
            }
        }
    }

    renderSuggestions() {
        const list = this.ref('suggestions');
        this.action('apply-suggestions').classList.toggle('hidden', this.suggestions.length === 0);

        list.replaceChildren(...this.suggestions.map((suggestion, index) => {
            const essence = this.essences.get(suggestion.essence_id);
            const item = document.createElement('li');
            item.innerHTML = `
                <label class="flex items-start gap-2 p-2 rounded-lg hover:bg-muted/50 cursor-pointer">
                    <input type="checkbox" class="mt-0.5 rounded border-border" data-suggestion="${index}" checked>
                    <span class="w-4 h-4 mt-0.5 rounded border border-border shrink-0"></span>
                    <span class="font-sans-soft text-xs text-foreground"></span>
                </label>`;
            item.querySelector('span.w-4').style.backgroundColor = essence?.hex;
            item.querySelector('span.font-sans-soft').textContent = `L${suggestion.row + 1} C${suggestion.col + 1} → ${essence?.code} ${essence?.name} : ${suggestion.reason}`;
            item.addEventListener('mouseenter', () => this.editor.setHighlight(suggestion));
            item.addEventListener('mouseleave', () => this.editor.setHighlight(null));
            return item;
        }));
    }

    applySuggestions() {
        const changes = [...this.ref('suggestions').querySelectorAll('[data-suggestion]:checked')]
            .map((checkbox) => this.suggestions[Number(checkbox.dataset.suggestion)])
            .map((suggestion) => [suggestion.row, suggestion.col, suggestion.essence_id]);

        this.editor.setHighlight(null);
        this.editor.apply(changes);
        this.suggestions = [];
        this.renderSuggestions();
    }

    /* ---------------------------------------------------------------- Result */

    scheduleRender() {
        const grid = this.editor.current();
        if (!grid) {
            return;
        }

        drawGrid(this.ref('result-flat'), grid, this.essences, Math.max(2, Math.floor(512 / Math.max(grid.length, grid[0].length))));
        drawGrid(this.ref('thumb-flat'), grid, this.essences, Math.max(1, Math.floor(96 / Math.max(grid.length, grid[0].length))));
        const edits = this.editor.edits.size;
        this.ref('editor-info').textContent = `Version n° ${this.selectedId} · ${edits} retouche${edits > 1 ? 's' : ''}`;
        const used = counts(grid);
        this.ref('result-info').textContent = `${used.size} essence(s) · ${grid.length * grid[0].length} morceaux`;

        const missing = [...used.keys()].filter((id) => !this.palette.texturedIds.includes(id)).map((id) => this.essences.get(id)?.name);
        this.ref('missing-textures').textContent = missing.length ? `Sans photo (rendu en aplat) : ${missing.join(', ')}` : '';

        clearTimeout(this.renderTimer);
        this.renderTimer = setTimeout(() => this.renderServerPreviews(grid), RENDER_DELAY_MS);
    }

    renderData(grid, kind) {
        const version = this.selectedVersion();
        const data = this.formData({ backgroundId: version?.backgroundId ?? this.backgroundId() });
        data.append('grid', JSON.stringify(grid));
        data.append('kind', kind);
        data.append('name', this.imageName);
        if (version?.kind === 'ai') {
            data.append('model', this.config.ai.imageModel);
        }
        return data;
    }

    async renderServerPreviews(grid) {
        this.renderController?.abort();
        this.renderController = new AbortController();
        const { signal } = this.renderController;

        try {
            const [wood, plan] = await Promise.all([
                postBlob(this.config.routes.render, this.renderData(grid, 'wood'), signal),
                postBlob(this.config.routes.render, this.renderData(grid, 'plan'), signal),
            ]);
            this.showBlob('result-wood', wood.blob);
            this.showBlob('result-plan', plan.blob);
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.handleFailure(error);
            }
        }
    }

    showBlob(ref, blob) {
        if (this.objectUrls[ref]) {
            URL.revokeObjectURL(this.objectUrls[ref]);
        }
        this.objectUrls[ref] = URL.createObjectURL(blob);
        this.ref(ref).src = this.objectUrls[ref];
        this.ref(ref.replace('result-', 'thumb-')).src = this.objectUrls[ref];
    }

    async downloadRender(kind) {
        const grid = this.editor.current();
        if (!grid) {
            return;
        }
        try {
            const { blob, filename } = await postBlob(this.config.routes.render, this.renderData(grid, kind));
            download(blob, filename);
        } catch (error) {
            this.handleFailure(error);
        }
    }

    async downloadArchive() {
        const grid = this.editor.current();
        if (!grid) {
            return;
        }
        const data = this.renderData(grid, 'zip');
        data.delete('kind');
        try {
            const { blob, filename } = await postBlob(this.config.routes.archive, data);
            download(blob, filename);
        } catch (error) {
            this.handleFailure(error);
        }
    }

    /* ---------------------------------------------------------------- Messages */

    setStatus(message) {
        const status = this.ref('status');
        status.textContent = message;
        status.classList.toggle('hidden', !message);
        if (message) {
            this.ref('ai-progress-message').textContent = message;
        }
    }

    addWarning(message) {
        const item = document.createElement('li');
        item.textContent = `⚠ ${message}`;
        this.ref('warnings').append(item);
    }

    showError(message) {
        const error = this.ref('error');
        error.textContent = message;
        error.classList.remove('hidden');
        error.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    hideError() {
        this.ref('error').classList.add('hidden');
    }
}

const root = document.getElementById('fragment-tool');
if (root) {
    new FragmentTool(root);
}
