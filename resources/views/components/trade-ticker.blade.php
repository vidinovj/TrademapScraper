{{-- resources/views/components/trade-ticker.blade.php --}}
<div class="trade-ticker-container">
    <div class="ticker-header">
        <span class="ticker-label">
            <i class="fas fa-chart-line me-2"></i>
            DATA TERBARU
        </span>
    </div>
    <div class="ticker-content" id="tradeTicker">
        <div class="ticker-scroll" id="tickerScroll">
            <!-- Data akan diisi via JavaScript -->
            <div class="ticker-item loading">
                <i class="fas fa-spinner fa-spin me-2"></i>
                Memuat data terbaru...
            </div>
        </div>
    </div>
</div>

<style>
.trade-ticker-container {
    background: linear-gradient(135deg, var(--pustik-primary) 0%, #3b82f6 100%);
    color: white;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    position: relative;
    margin-bottom: 1.5rem;
    border-radius: 0.5rem;
}

.ticker-header {
    background: rgba(0, 0, 0, 0.1);
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    font-weight: 600;
    font-size: 0.875rem;
    letter-spacing: 0.025em;
}

.ticker-content {
    height: 60px;
    overflow: hidden;
    position: relative;
    display: flex;
    align-items: center;
}

.ticker-scroll {
    display: flex;
    align-items: center;
    white-space: nowrap;
    animation: scrollLeft 120s linear infinite;
    gap: 3rem;
}

.ticker-item {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    flex-shrink: 0;
    border-right: 1px solid rgba(255, 255, 255, 0.2);
    min-width: 300px;
}

.ticker-item:last-child {
    border-right: none;
}

.ticker-item.loading {
    opacity: 0.7;
    font-style: italic;
}

.ticker-item .item-label {
    color: rgba(255, 255, 255, 0.8);
    margin-right: 0.5rem;
    font-size: 0.8rem;
}

.ticker-item .item-value {
    font-weight: 700;
    margin-right: 0.5rem;
}

.ticker-item .item-change {
    font-size: 0.8rem;
    padding: 0.2rem 0.5rem;
    border-radius: 0.25rem;
    font-weight: 600;
}

.ticker-item .item-change.positive {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.ticker-item .item-change.negative {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.ticker-item .item-change.neutral {
    background: rgba(156, 163, 175, 0.2);
    color: #9ca3af;
}

.ticker-item .hs-code {
    background: rgba(255, 255, 255, 0.15);
    padding: 0.2rem 0.5rem;
    border-radius: 0.25rem;
    font-family: 'Consolas', monospace;
    font-size: 0.75rem;
    margin-right: 0.5rem;
}

@keyframes scrollLeft {
    0% {
        transform: translateX(100%);
    }
    100% {
        transform: translateX(-100%);
    }
}

/* Pause animation on hover */
.ticker-scroll:hover {
    animation-play-state: paused;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .ticker-item {
        min-width: 250px;
        font-size: 0.8rem;
    }
    
    .ticker-scroll {
        animation-duration: 90s;
    }
}

/* Speed variations */
.ticker-scroll.speed-slow {
    animation-duration: 180s;
}

.ticker-scroll.speed-normal {
    animation-duration: 120s;
}

.ticker-scroll.speed-fast {
    animation-duration: 60s;
}

/* Different ticker styles */
.trade-ticker-container.style-urgent {
    background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
}

.trade-ticker-container.style-success {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
}

.trade-ticker-container.style-warning {
    background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
}

/* Subtle glow effect */
.trade-ticker-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    animation: shine 3s infinite;
}

@keyframes shine {
    0% { left: -100%; }
    100% { left: 100%; }
}
</style>

<script>
class TradeTicker {
    constructor() {
        this.apiUrl = '/api/trade-data-latest';
        this.updateInterval = 30000; // 30 seconds
        this.tickerElement = document.getElementById('tickerScroll');
        this.isLoading = false;
        
        this.init();
    }
    
    init() {
        console.log('Initializing TradeTicker...');
        this.loadLatestData();
        this.startAutoUpdate();
        this.setupEventListeners();
    }
    
    async loadLatestData() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        
        try {
            const response = await fetch(this.apiUrl);
            const data = await response.json();
            
            if (data.success && data.trades) {
                this.updateTicker(data.trades);
            }
        } catch (error) {
            console.error('Error loading ticker data:', error);
            this.showError();
        } finally {
            this.isLoading = false;
        }
    }
    
    updateTicker(trades) {
        if (!trades || trades.length === 0) return;
        
        let tickerHTML = '';
        
        trades.forEach(trade => {
            const changeClass = this.getChangeClass(trade.change_percent);
            const changeIcon = trade.change_percent > 0 ? '↗' : trade.change_percent < 0 ? '↘' : '→';
            
            tickerHTML += `
                <div class="ticker-item">
                    <span class="hs-code">${trade.kode_hs}</span>
                    <span class="item-label">${this.truncateText(trade.label, 30)}</span>
                    <span class="item-value">${this.formatNumber(trade.nilai)}</span>
                    <span class="item-change ${changeClass}">
                        ${changeIcon} ${Math.abs(trade.change_percent || 0)}%
                    </span>
                </div>
            `;
        });
        
        // Add summary data
        const totalValue = trades.reduce((sum, trade) => sum + parseFloat(trade.nilai || 0), 0);
        tickerHTML += `
            <div class="ticker-item">
                <i class="fas fa-chart-bar me-2"></i>
                <span class="item-label">Total Hari Ini:</span>
                <span class="item-value">${this.formatNumber(totalValue)}</span>
            </div>
        `;
        
        // Add timestamp
        tickerHTML += `
            <div class="ticker-item">
                <i class="fas fa-clock me-2"></i>
                <span class="item-label">Update:</span>
                <span class="item-value">${new Date().toLocaleTimeString('id-ID')}</span>
            </div>
        `;
        
        this.tickerElement.innerHTML = tickerHTML;
    }
    
    getChangeClass(changePercent) {
        if (changePercent > 0) return 'positive';
        if (changePercent < 0) return 'negative';
        return 'neutral';
    }
    
    formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toLocaleString();
    }
    
    truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }
    
    showError() {
        this.tickerElement.innerHTML = `
            <div class="ticker-item loading">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Gagal memuat data. Mencoba lagi...
            </div>
        `;
    }
    
    startAutoUpdate() {
        setInterval(() => {
            this.loadLatestData();
        }, this.updateInterval);
    }
    
    setupEventListeners() {
        // Pause on hover
        this.tickerElement.addEventListener('mouseenter', () => {
            this.tickerElement.style.animationPlayState = 'paused';
        });
        
        this.tickerElement.addEventListener('mouseleave', () => {
            this.tickerElement.style.animationPlayState = 'running';
        });
    }
    
    // Public methods for controlling ticker
    pause() {
        this.tickerElement.style.animationPlayState = 'paused';
    }
    
    resume() {
        this.tickerElement.style.animationPlayState = 'running';
    }
    
    setSpeed(speed) {
        this.tickerElement.className = `ticker-scroll speed-${speed}`;
    }
    
    setStyle(style) {
        const container = document.querySelector('.trade-ticker-container');
        container.className = `trade-ticker-container style-${style}`;
    }
}

// Initialize ticker when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.tradeTicker = new TradeTicker();
});
</script>