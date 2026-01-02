"use strict";

/**
 * MOKUSÓ GYM TIMER - MASTER V6.9 (Super Responsive Fix)
 * - Solución Layout: Cambia a columna vertical automáticamente en pantallas medianas/pequeñas.
 * - Solución Scroll: Permite scroll vertical para evitar elementos encimados.
 * - Enlace corregido: Apunta a gym-timer.html.
 */

const MokusoTimer = {
    // --- CONFIGURACIÓN ---
    config: {
        storageKey: 'mokuso_master_v6_full',
        workerPath: 'assets/js/timer-worker.js',
        dashboardUrl: 'dashboard/', 
        durations: {
            prep: 6,      // A1.mp3
            alert: 10     // A2.mp3
        }
    },

    // --- ESTADO ---
    state: {
        workoutSeconds: 60, restSeconds: 10, totalReps: 8,
        remainingReps: 8, remainingSeconds: 0, currentPhase: 'idle',
        isRunning: false, timerMode: 'countdown',
        _flags: { alertA2Played: false, alertA1Played: false }
    },

    internal: { worker: null, lastTickTime: 0, targetEndTime: 0, wakeLock: null },

    // --- AUDIO ENGINE ---
    audio: {
        initialized: false,
        sounds: {},
        files: {
            a1: 'assets/audios/a1.mp3', // PREPARACIÓN
            a2: 'assets/audios/a2.mp3', // FIN TRABAJO
        },
        init() {
            if (this.initialized) return;
            try {
                for (const [k, p] of Object.entries(this.files)) {
                    this.sounds[k] = new Audio(p);
                    this.sounds[k].preload = 'auto';
                }
                this.initialized = true;
                console.log("🔊 Audio Listo");
            } catch (e) { console.error(e); }
        },
        stopAll() {
            Object.values(this.sounds).forEach(s => {
                s.pause(); s.currentTime = 0; s.loop = false;
            });
        },
        play(name, loop = false) {
            if (!this.initialized) return;
            if (!loop) this.stopAll();
            
            const s = this.sounds[name];
            if (s) {
                s.loop = loop;
                s.play().catch(() => {});
            }
        }
    },

    // ========================================================================
    // INICIALIZACIÓN
    // ========================================================================
    init() {
        const isDisplay = new URLSearchParams(window.location.search).get('display') === 'true';
        if (isDisplay) {
            this.Render.displayView();
            this.initDisplayListeners();
        } else {
            this.Render.adminView();
            this.initAdminListeners();
            this.initWorker();
            this.loadState();
            this.Render.updateAdminPreview();
        }
        // Eliminar loader si existe
        const loader = document.getElementById('loader');
        if (loader) {
            setTimeout(() => loader.classList.add('fade-out'), 300);
            setTimeout(() => loader.style.display = 'none', 800);
        }
    },

    initWorker() {
        try {
            this.internal.worker = new Worker(this.config.workerPath);
            this.internal.worker.onmessage = (e) => { if (e.data.type === 'TICK') this.handleTick(); };
        } catch (e) {
            this.internal.worker = { postMessage: (m) => { if (m.command === 'START') { if (!this.internal.f) this.internal.f = setInterval(() => this.handleTick(), 250); } else { clearInterval(this.internal.f); this.internal.f = null; } } };
        }
    },
    async requestWakeLock() { try { if ('wakeLock' in navigator) this.internal.wakeLock = await navigator.wakeLock.request('screen'); } catch (e) {} },

    // ========================================================================
    // LÓGICA CORE
    // ========================================================================
    startTimerEngine() {
        this.internal.lastTickTime = Date.now();
        if (this.state.timerMode === 'countdown') this.internal.targetEndTime = Date.now() + (this.state.remainingSeconds * 1000);
        this.requestWakeLock();
        if (this.internal.worker) this.internal.worker.postMessage({ command: 'START' });
    },
    stopTimerEngine() {
        if (this.internal.worker) this.internal.worker.postMessage({ command: 'STOP' });
        if (this.internal.wakeLock) { this.internal.wakeLock.release(); this.internal.wakeLock = null; }
    },
    handleTick() {
        if (!this.state.isRunning) return;
        const now = Date.now();
        if (this.state.timerMode === 'countdown') {
            const timeLeft = Math.ceil((this.internal.targetEndTime - now) / 1000);
            if (timeLeft !== this.state.remainingSeconds) {
                this.state.remainingSeconds = timeLeft;
                this.checkAudioEvents();
                if (this.state.remainingSeconds <= 0) { this.state.remainingSeconds = 0; this.switchPhase(); }
                this.saveState(); this.Render.updateAdminPreview();
            }
        } else {
            if (now - this.internal.lastTickTime >= 1000) {
                this.state.remainingSeconds += Math.floor((now - this.internal.lastTickTime) / 1000);
                this.internal.lastTickTime = now;
                this.saveState(); this.Render.updateAdminPreview();
            }
        }
    },
    checkAudioEvents() {
        if (this.state.timerMode !== 'countdown') return;
        const s = this.state;
        if (s.currentPhase === 'workout' && s.remainingSeconds <= this.config.durations.alert && s.remainingSeconds > 0 && !s._flags.alertA2Played) {
            this.audio.play('a2'); s._flags.alertA2Played = true;
        }
        if (s.currentPhase === 'rest' && s.remainingSeconds <= this.config.durations.prep && s.remainingSeconds > 0 && !s._flags.alertA1Played) {
            this.audio.play('a1'); s._flags.alertA1Played = true;
        }
    },
    switchPhase() {
        const old = this.state.currentPhase;
        if (old === 'prep') {
            this.state.currentPhase = (this.state.timerMode === 'countdown') ? 'workout' : 'stopwatch';
            this.state.remainingSeconds = (this.state.timerMode === 'countdown') ? this.state.workoutSeconds : 0;
        } else if (old === 'workout' || old === 'stopwatch') {
            this.state.remainingReps--;
            if (this.state.remainingReps > 0) { this.state.currentPhase = 'rest'; this.state.remainingSeconds = this.state.restSeconds; }
            else { this.reset(); return; }
        } else if (old === 'rest') {
            this.state.currentPhase = 'workout'; this.state.remainingSeconds = this.state.workoutSeconds;
        }
        this.internal.targetEndTime = Date.now() + (this.state.remainingSeconds * 1000);
        this.state._flags.alertA2Played = false; this.state._flags.alertA1Played = false;
        if (this.state.currentPhase === 'workout') this.audio.stopAll();
        this.saveState();
    },
    toggleStartPause() {
        this.state.isRunning = !this.state.isRunning;
        if (this.state.isRunning) {
            if (!this.audio.initialized) this.audio.init();
            if (this.state.currentPhase === 'idle') {
                this.state.currentPhase = 'prep';
                this.state.remainingSeconds = this.config.durations.prep;
                this.state.remainingReps = this.state.totalReps;
                this.audio.play('a1'); 
            } 
            this.startTimerEngine();
        } else {
            this.stopTimerEngine(); this.audio.stopAll();
        }
        this.saveState(); this.Render.updateAdminButtons();
    },
    reset() {
        this.stopTimerEngine(); this.audio.stopAll();
        this.state.isRunning = false; this.state.currentPhase = 'idle';
        this.state.remainingReps = this.state.totalReps;
        this.state.remainingSeconds = (this.state.timerMode === 'countdown') ? this.config.durations.prep : 0;
        this.state._flags.alertA2Played = false; this.state._flags.alertA1Played = false;
        this.saveState(); this.Render.updateAdminButtons(); this.Render.updateAdminPreview();
    },
    applySettings(s) {
        this.state.workoutSeconds = parseInt(s.work)||60; this.state.restSeconds = parseInt(s.rest)||10;
        this.state.totalReps = parseInt(s.reps)||8; this.state.timerMode = s.mode||'countdown';
        if (!this.state.isRunning) this.reset(); else this.saveState();
    },
    saveState() { localStorage.setItem(this.config.storageKey, JSON.stringify(this.state)); },
    loadState() { const s = localStorage.getItem(this.config.storageKey); if (s) try { this.state = {...this.state, ...JSON.parse(s)}; } catch (e) {} },

    // ========================================================================
    // RENDERIZADO (UI - SUPER RESPONSIVE)
    // ========================================================================
    Render: {
        adminView() {
            document.getElementById('app').innerHTML = `
                <!-- Usamos min-h-screen para permitir scroll si la pantalla es pequeña -->
                <div class="min-h-screen w-full flex flex-col items-center justify-center p-4 md:p-6 lg:p-8 font-sans bg-mokuso-dark overflow-y-auto">
                    
                    <!-- Botón Dashboard Flotante -->
                    <a href="${MokusoTimer.config.dashboardUrl}" class="absolute top-4 left-4 md:top-8 md:left-8 flex items-center gap-2 text-gray-400 hover:text-white transition-colors z-20 bg-black/20 px-3 py-2 rounded-full backdrop-blur-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        <span class="text-xs font-bold tracking-wider">SALIR</span>
                    </a>

                    <div class="w-full max-w-6xl grid grid-cols-1 xl:grid-cols-12 gap-6 z-10 mt-12 md:mt-0">
                        
                        <!-- PANEL DE CONTROL (IZQUIERDA) -->
                        <!-- Cambia a col-span-full en pantallas medianas para evitar encimarse -->
                        <div class="col-span-1 xl:col-span-7 bg-gray-900/80 backdrop-blur-xl border border-white/10 rounded-3xl p-5 md:p-8 shadow-2xl">
                            <header class="flex items-center gap-4 mb-6 border-b border-white/5 pb-4">
                                <img src="assets/img/logo2.png" class="h-10 w-10 md:h-12 md:w-12 object-contain drop-shadow-[0_0_10px_rgba(123,227,81,0.5)]" onerror="this.style.display='none'">
                                <div>
                                    <h1 class="font-mono text-xl md:text-2xl text-white font-bold tracking-wider">MOKUSÓ</h1>
                                    <p class="text-[10px] md:text-xs text-mokuso-green tracking-[0.3em] font-bold">CONTROLLER</p>
                                </div>
                            </header>

                            <div class="space-y-5">
                                <!-- FILA 1: MODO Y SERIES -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                                    <div class="space-y-2">
                                        <label class="text-[10px] text-gray-400 font-bold tracking-widest">MODO</label>
                                        <select id="opt-mode" class="w-full bg-black/40 border border-white/10 text-white rounded-xl px-4 py-3 focus:border-mokuso-green outline-none text-sm md:text-base">
                                            <option value="countdown">Cuenta Regresiva</option>
                                            <option value="stopwatch">Cronómetro</option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[10px] text-gray-400 font-bold tracking-widest">SERIES</label>
                                        <div class="flex gap-2">
                                            <input type="number" id="opt-reps" class="w-20 bg-black/40 border border-white/10 text-white rounded-xl px-2 py-3 text-center font-mono text-lg focus:border-mokuso-green outline-none" value="8" min="0">
                                            <div class="flex flex-1 gap-1 overflow-x-auto">
                                                <button class="btn-preset js-reps flex-1" data-val="3">3</button>
                                                <button class="btn-preset js-reps flex-1" data-val="5">5</button>
                                                <button class="btn-preset js-reps flex-1" data-val="8">8</button>
                                                <button class="btn-preset js-reps flex-1" data-val="10">10</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- FILA 2: TIEMPOS -->
                                <div class="space-y-2">
                                    <label class="text-[10px] text-gray-400 font-bold tracking-widest">TIEMPOS (SEGUNDOS)</label>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="bg-black/20 p-3 md:p-4 rounded-xl border border-white/5">
                                            <span class="text-mokuso-green text-[10px] font-bold block mb-1">TRABAJO</span>
                                            <input type="number" id="opt-work" class="w-full bg-transparent text-2xl md:text-3xl font-mono font-bold text-white outline-none placeholder-gray-700" value="60" min="1">
                                        </div>
                                        <div class="bg-black/20 p-3 md:p-4 rounded-xl border border-white/5">
                                            <span class="text-mokuso-accent text-[10px] font-bold block mb-1">DESCANSO</span>
                                            <input type="number" id="opt-rest" class="w-full bg-transparent text-2xl md:text-3xl font-mono font-bold text-white outline-none placeholder-gray-700" value="10" min="0">
                                        </div>
                                    </div>
                                    <!-- PRESETS TIEMPO (Scroll horizontal en móvil) -->
                                    <div class="flex gap-2 mt-2 overflow-x-auto pb-2 scrollbar-hide">
                                        <button class="btn-preset-lg js-time shrink-0" data-val="30">30s</button>
                                        <button class="btn-preset-lg js-time shrink-0" data-val="45">45s</button>
                                        <button class="btn-preset-lg js-time shrink-0" data-val="60">1m</button>
                                        <button class="btn-preset-lg js-time shrink-0" data-val="180">3m</button>
                                        <button class="btn-preset-lg js-time shrink-0" data-val="300">5m</button>
                                    </div>
                                </div>

                                <!-- BOTONES ACCIÓN -->
                                <div class="flex gap-3 pt-4 border-t border-white/5">
                                    <button id="btn-apply" class="flex-1 bg-white/10 hover:bg-white/20 text-white font-bold py-3 rounded-xl transition-all tracking-wide text-sm md:text-base border border-white/10">ESTABLECER</button>
                                    <button id="btn-reset" class="flex-1 bg-red-500/10 hover:bg-red-500/20 text-red-400 font-bold py-3 rounded-xl transition-all tracking-wide text-sm md:text-base border border-red-500/20">REINICIAR</button>
                                </div>
                            </div>
                        </div>

                        <!-- PANEL VISTA PREVIA Y BOTONES GRANDES -->
                        <div class="col-span-1 xl:col-span-5 flex flex-col gap-4 md:gap-6">
                            
                            <!-- MONITOR -->
                            <div class="bg-black border-2 border-gray-800 rounded-3xl p-6 md:p-8 relative overflow-hidden shadow-2xl flex flex-col items-center justify-center min-h-[250px] md:min-h-[300px]">
                                <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-gray-800/20 to-transparent pointer-events-none"></div>
                                <span class="text-gray-500 text-[9px] font-bold tracking-[0.3em] absolute top-4 md:top-6">VISTA PREVIA</span>
                                
                                <!-- Texto fluido con Clamp -->
                                <div id="preview-time" class="font-mono text-[clamp(4rem,10vw,6rem)] font-bold text-white drop-shadow-lg transition-colors duration-300">00:00</div>
                                
                                <div class="flex items-center gap-4 mt-2 md:mt-4">
                                    <div id="preview-phase" class="px-3 py-1 rounded bg-gray-800 text-gray-300 text-[10px] md:text-xs font-bold tracking-wider">LISTO</div>
                                    <div id="preview-reps" class="text-gray-400 text-[10px] md:text-xs font-mono">0 / 0</div>
                                </div>
                            </div>

                            <!-- BOTÓN INICIAR GIGANTE -->
                            <button id="btn-toggle" class="w-full bg-gradient-to-r from-mokuso-green to-emerald-600 hover:from-emerald-400 hover:to-emerald-500 text-black font-black text-lg md:text-xl py-5 md:py-6 rounded-2xl shadow-[0_0_30px_rgba(123,227,81,0.3)] transition-all uppercase tracking-widest active:scale-95">
                                INICIAR
                            </button>

                            <!-- ENLACE CORREGIDO: gym-timer.html -->
                            <button onclick="window.open('gym_timer.html?display=true', 'MokusoDisplay', 'width=1920,height=1080')" class="w-full flex items-center justify-center gap-2 text-mokuso-accent hover:text-white font-bold text-xs md:text-sm transition-colors py-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                                ABRIR PANTALLA GIGANTE
                            </button>
                        </div>

                    </div>
                </div>
            `;
        },
        displayView() {
            document.getElementById('app').innerHTML = `
                <div class="relative w-full h-[100dvh] flex flex-col items-center justify-center overflow-hidden bg-mokuso-dark">
                    
                    <!-- Header -->
                    <header class="absolute top-0 w-full p-4 md:p-8 flex justify-between items-start z-30">
                        <div class="flex flex-col gap-1 opacity-80">
                            <div class="flex items-center gap-3">
                                <img src="assets/img/logo2.png" class="h-10 md:h-16 w-auto object-contain drop-shadow-[0_0_15px_rgba(255,255,255,0.3)]" onerror="this.style.opacity='0'">
                                <h1 class="font-mono text-xl md:text-3xl font-bold tracking-widest text-white hidden md:block">MOKUSÓ</h1>
                            </div>
                        </div>
                        <div class="text-right">
                            <div id="clock-time" class="font-mono text-2xl md:text-4xl font-bold text-white drop-shadow-md">--:--</div>
                            <div id="clock-date" class="text-gray-400 text-xs md:text-sm font-bold tracking-wider uppercase mt-1">--/--</div>
                        </div>
                    </header>

                    <!-- Marca de agua -->
                    <img src="assets/img/logo2.png" class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[50vh] opacity-[0.03] pointer-events-none z-0" onerror="this.style.display='none'">

                    <!-- Contenido Principal -->
                    <main class="relative z-20 flex flex-col items-center text-center w-full px-4">
                        <!-- Fase -->
                        <h2 id="phase-label" class="text-[clamp(2rem,6vw,4rem)] font-black tracking-[0.3em] md:tracking-[0.5em] text-gray-500 mb-2 md:mb-4 transition-colors duration-300 leading-none">LISTO</h2>
                        
                        <!-- Timer Gigante Inteligente: min(ancho, alto) -->
                        <div id="timer-digits" class="font-mono text-[min(35vw,50vh)] leading-[0.9] font-bold text-white tabular-nums drop-shadow-[0_0_40px_rgba(255,255,255,0.2)] transition-colors duration-300">
                            00:00
                        </div>

                        <!-- Contador de Series -->
                        <div id="reps-container" class="mt-4 md:mt-8 flex flex-col items-center gap-1 md:gap-2 opacity-0 transition-opacity duration-500">
                            <span class="text-xs md:text-xl text-gray-400 font-bold tracking-widest uppercase">SERIES RESTANTES</span>
                            <div id="reps-val" class="font-mono text-3xl md:text-6xl font-bold text-white">0</div>
                        </div>
                    </main>

                    <!-- Overlay de Inicio -->
                    <div id="start-overlay" class="fixed inset-0 z-50 bg-black/95 backdrop-blur-md flex flex-col items-center justify-center transition-opacity duration-500">
                        <h1 class="font-mono text-5xl md:text-8xl font-black text-transparent bg-clip-text bg-gradient-to-b from-white to-gray-500 mb-8 tracking-tighter">MOKUSÓ</h1>
                        <button id="btn-activate" class="group relative px-8 py-4 md:px-12 md:py-6 bg-mokuso-green text-black font-black text-lg md:text-2xl tracking-widest uppercase rounded-full overflow-hidden shadow-[0_0_40px_rgba(123,227,81,0.4)] hover:shadow-[0_0_80px_rgba(123,227,81,0.6)] hover:scale-105 transition-all duration-300">
                            <span class="relative z-10">ACTIVAR PANTALLA</span>
                            <div class="absolute inset-0 bg-white/30 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                        </button>
                        <p class="mt-8 text-gray-500 text-xs md:text-sm tracking-widest animate-pulse">CLICK PARA INICIAR SONIDO</p>
                    </div>
                </div>
            `;
            this.startClock();
        },
        updateAdminPreview() {
            const t = document.getElementById('preview-time'), p = document.getElementById('preview-phase'), r = document.getElementById('preview-reps');
            if (t) { 
                t.textContent = MokusoTimer.util.format(MokusoTimer.state.remainingSeconds);
                const baseClass = "font-mono text-[clamp(4rem,10vw,6rem)] font-bold drop-shadow-lg transition-colors duration-300";
                if (MokusoTimer.state.currentPhase === 'rest') t.className = baseClass + " text-mokuso-accent";
                else if (MokusoTimer.state.currentPhase === 'workout') t.className = baseClass + " text-mokuso-green";
                else if (MokusoTimer.state.currentPhase === 'prep') t.className = baseClass + " text-yellow-400";
                else t.className = baseClass + " text-white";
            }
            if (p) p.textContent = MokusoTimer.state.currentPhase === 'prep' ? 'PREPARAR' : (MokusoTimer.state.currentPhase === 'idle' ? 'LISTO' : MokusoTimer.state.currentPhase.toUpperCase());
            if (r) r.textContent = `${MokusoTimer.state.totalReps > 0 ? MokusoTimer.state.remainingReps : 0} / ${MokusoTimer.state.totalReps}`;
        },
        updateAdminButtons() { 
            const b = document.getElementById('btn-toggle'); 
            if(b){ 
                if (MokusoTimer.state.isRunning) {
                    b.textContent = 'PAUSAR';
                    b.className = "w-full bg-yellow-500 hover:bg-yellow-400 text-black font-black text-lg md:text-xl py-5 md:py-6 rounded-2xl shadow-[0_0_30px_rgba(234,179,8,0.3)] transition-all uppercase tracking-widest animate-pulse";
                } else {
                    b.textContent = (MokusoTimer.state.currentPhase === 'idle' ? 'INICIAR' : 'REANUDAR');
                    b.className = "w-full bg-gradient-to-r from-mokuso-green to-emerald-600 hover:from-emerald-400 hover:to-emerald-500 text-black font-black text-lg md:text-xl py-5 md:py-6 rounded-2xl shadow-[0_0_30px_rgba(123,227,81,0.3)] hover:shadow-[0_0_50px_rgba(123,227,81,0.5)] hover:scale-[1.02] active:scale-[0.98] transition-all uppercase tracking-widest";
                }
            } 
        },
        updateDisplayUI(s) {
            const t = document.getElementById('timer-digits'), p = document.getElementById('phase-label'), rc = document.getElementById('reps-container'), rv = document.getElementById('reps-val');
            if (!t) return;
            
            t.textContent = MokusoTimer.util.format(s.remainingSeconds);
            
            const baseDigits = "font-mono text-[min(35vw,50vh)] leading-[0.9] font-bold tabular-nums drop-shadow-[0_0_40px_rgba(255,255,255,0.2)] transition-colors duration-300";
            const baseLabel = "text-[clamp(2rem,6vw,4rem)] font-black tracking-[0.3em] md:tracking-[0.5em] mb-2 md:mb-4 transition-colors duration-300 leading-none";

            if (s.currentPhase === 'prep') { 
                p.textContent = 'PREPARAR'; 
                p.className = baseLabel + " text-yellow-400 animate-pulse";
                t.className = baseDigits + " text-yellow-400 drop-shadow-[0_0_60px_rgba(250,204,21,0.6)]";
            }
            else if (s.currentPhase === 'workout' || s.currentPhase === 'stopwatch') { 
                p.textContent = (s.timerMode === 'stopwatch') ? 'CRONÓMETRO' : 'TRABAJO'; 
                p.className = baseLabel + " text-mokuso-green";
                t.className = baseDigits + " text-mokuso-green drop-shadow-[0_0_60px_rgba(123,227,81,0.6)]";
            }
            else if (s.currentPhase === 'rest') { 
                p.textContent = 'DESCANSAR'; 
                p.className = baseLabel + " text-mokuso-accent";
                t.className = baseDigits + " text-mokuso-accent drop-shadow-[0_0_60px_rgba(56,189,248,0.6)]";
            }
            else { 
                p.textContent = 'MOKUSÓ GYM'; 
                p.className = baseLabel + " text-gray-500";
                t.className = baseDigits + " text-white";
            }

            if (s.isRunning && s.timerMode === 'countdown' && s.currentPhase === 'workout' && s.remainingSeconds <= MokusoTimer.config.durations.alert && s.remainingSeconds > 0) {
                t.classList.add('text-red-500', 'drop-shadow-[0_0_80px_rgba(239,68,68,0.8)]', 'animate-pulse');
                t.classList.remove('text-mokuso-green');
            }

            if (s.totalReps > 0 && s.currentPhase !== 'idle' && s.timerMode !== 'stopwatch') { 
                rc.classList.remove('opacity-0'); 
                rv.textContent = s.remainingReps; 
            } else { 
                rc.classList.add('opacity-0'); 
            }
        },
        startClock() { setInterval(() => { const d = new Date(); const t = document.getElementById('clock-time'), dt = document.getElementById('clock-date'); if(t) t.textContent = d.toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'}); if(dt) dt.textContent = d.toLocaleDateString('es-MX',{day:'2-digit',month:'short'}); }, 1000); }
    },

    initAdminListeners() {
        document.getElementById('btn-toggle').onclick = () => this.toggleStartPause();
        document.getElementById('btn-reset').onclick = () => this.reset();
        document.getElementById('btn-apply').onclick = () => this.applySettings({ mode: document.getElementById('opt-mode').value, reps: document.getElementById('opt-reps').value, work: document.getElementById('opt-work').value, rest: document.getElementById('opt-rest').value });
        
        document.querySelectorAll('.js-time').forEach(b => b.onclick = (e) => {
            document.querySelectorAll('.js-time').forEach(btn => btn.classList.remove('bg-white', 'text-black'));
            document.querySelectorAll('.js-time').forEach(btn => btn.classList.add('bg-black/30', 'text-white'));
            e.target.classList.remove('bg-black/30', 'text-white');
            e.target.classList.add('bg-white', 'text-black');
            document.getElementById('opt-work').value = e.target.dataset.val;
        });

        document.querySelectorAll('.js-reps').forEach(b => b.onclick = (e) => {
             document.querySelectorAll('.js-reps').forEach(btn => btn.classList.remove('bg-mokuso-green', 'text-black'));
             document.querySelectorAll('.js-reps').forEach(btn => btn.classList.add('bg-black/30', 'text-white'));
             e.target.classList.remove('bg-black/30', 'text-white');
             e.target.classList.add('bg-mokuso-green', 'text-black');
             document.getElementById('opt-reps').value = e.target.dataset.val;
        });
    },
    initDisplayListeners() {
        document.getElementById('btn-activate').onclick = () => { 
            this.audio.init(); 
            const overlay = document.getElementById('start-overlay');
            overlay.classList.add('opacity-0', 'pointer-events-none');
            this.loadState(); 
        };
        window.addEventListener('storage', (e) => {
            if (e.key === this.config.storageKey && e.newValue) {
                const ns = JSON.parse(e.newValue);
                this.state = { ...this.state, ...ns };
                this.Render.updateDisplayUI(this.state);
                if (this.audio.initialized) {
                    if (!ns.isRunning) this.audio.stopAll();
                }
            }
        });
    },

    util: { format(s) { const n = Math.max(0, parseInt(s)); return `${Math.floor(n / 60).toString().padStart(2,'0')}:${(n % 60).toString().padStart(2,'0')}`; } }
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => MokusoTimer.init());
else MokusoTimer.init();

const style = document.createElement('style');
style.innerHTML = `
    .btn-preset { @apply px-2 py-2 rounded-lg font-bold text-xs md:text-sm border border-white/10 transition-all hover:bg-white/10 bg-black/30; }
    .btn-preset-lg { @apply px-3 py-2 rounded-xl font-bold border border-white/10 transition-all hover:bg-white/10 bg-black/30 text-xs md:text-sm; }
`;
document.head.appendChild(style);