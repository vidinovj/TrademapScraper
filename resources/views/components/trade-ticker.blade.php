{{-- resources/views/components/trade-ticker.blade.php --}}
<div class="trade-ticker-container">
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
    width: calc(100% + 2rem);
    margin-left: -1rem;
    background-color: var(--pustik-bg-card);
    color: var(--pustik-text-dark);
    overflow: hidden;
    position: relative;
    height: 40px;
    border-bottom: 1px solid var(--pustik-border);
    margin-bottom: 0.5rem;
}

.ticker-content {
    height: 100%;
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
    gap: 2rem;
}

.ticker-item {
    display: inline-flex;
    align-items: center;
    padding: 0 1rem;
    font-size: 0.8rem;
    font-weight: 500;
    flex-shrink: 0;
    border-right: 1px solid var(--pustik-border);
    min-width: auto;
}

.ticker-item:last-child {
    border-right: none;
}

.ticker-item.loading {
    opacity: 0.7;
    font-style: italic;
}

.ticker-item .item-label {
    color: var(--pustik-text-dark);
    margin-right: 0.5rem;
    font-size: 0.75rem;
}

.ticker-item .item-value {
    font-weight: 600;
    margin-right: 0.5rem;
    color: var(--pustik-text-light);
}

.ticker-item .item-change {
    font-size: 0.8rem;
    font-weight: 600;
}

.ticker-item .item-change.positive {
    color: #22c55e; /* green-500 */
}

.ticker-item .item-change.negative {
    color: #ef4444; /* red-500 */
}

.ticker-item .item-change.neutral {
    color: #9ca3af; /* gray-400 */
}

.ticker-item .hs-code {
    background: rgba(59, 130, 246, 0.1);
    color: var(--pustik-primary);
    padding: 0.15rem 0.4rem;
    border-radius: 0.25rem;
    font-family: 'Consolas', monospace;
    font-size: 0.7rem;
    margin-right: 0.5rem;
}

@keyframes scrollLeft {
    0% {
        transform: translateX(0%);
    }
    100% {
        transform: translateX(-50%); /* Adjust based on duplicated content */
    }
}

/* Pause animation on hover */
.ticker-scroll:hover {
    animation-play-state: paused;
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
        if (!trades || trades.length === 0) {
            this.tickerElement.innerHTML = '<div class="ticker-item">No recent trade data available.</div>';
            return;
        }
        
        let tickerItemsHTML = '';
        
        trades.forEach(trade => {
            const changeClass = this.getChangeClass(trade.change_percent);
            const changeIcon = trade.change_percent > 0 ? '↗' : trade.change_percent < 0 ? '↘' : '→';
            
            tickerItemsHTML += `
                <div class="ticker-item">
                    <span class="hs-code">${trade.kode_hs}</span>
                    <span class="item-label">${this.truncateText(trade.label, 30)}</span>
                    <strong class="item-value">${this.formatNumber(trade.nilai)}</strong>
                    <span class="item-change ${changeClass}">
                        ${changeIcon} ${Math.abs(trade.change_percent || 0)}%
                    </span>
                </div>
            `;
        });
        
        // Duplicate the items for a seamless loop
        this.tickerElement.innerHTML = tickerItemsHTML + tickerItemsHTML;
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