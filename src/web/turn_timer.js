/**
 * MagellanWars Turn Timer System
 * Displays countdown to next turn and game statistics
 */

class TurnTimer {
    constructor() {
        this.timerInterval = null;
        this.updateInterval = null;
        this.nextTurnTime = null;
        this.currentTurn = 0;
        this.lastNotificationTime = 0;
    }
    
    init() {
        // Create the timer display element
        this.createTimerDisplay();
        
        // Start updating
        this.updateTurnStatus();
        
        // Update every second for countdown
        this.timerInterval = setInterval(() => this.updateCountdown(), 1000);
        
        // Fetch new status every 30 seconds
        this.updateInterval = setInterval(() => this.updateTurnStatus(), 30000);
    }
    
    createTimerDisplay() {
        const timerHtml = `
            <div id="turn-timer" style="
                position: fixed;
                top: 10px;
                right: 10px;
                background: linear-gradient(135deg, #1a1a2e, #16213e);
                border: 2px solid #0f3460;
                border-radius: 10px;
                padding: 15px;
                color: #e94560;
                font-family: 'Courier New', monospace;
                z-index: 10000;
                box-shadow: 0 4px 6px rgba(0,0,0,0.3);
                min-width: 250px;
            ">
                <div style="text-align: center; margin-bottom: 10px;">
                    <strong style="color: #f5f5f5; font-size: 14px;">⚡ TURN TIMER ⚡</strong>
                </div>
                <div id="turn-info" style="margin-bottom: 10px;">
                    <div style="color: #f5f5f5; font-size: 12px;">
                        Turn: <span id="current-turn" style="color: #4fbdba;">--</span>
                    </div>
                    <div style="color: #f5f5f5; font-size: 12px;">
                        Next Turn In: <span id="countdown" style="color: #7ec8e3; font-weight: bold;">--:--</span>
                    </div>
                </div>
                <div id="game-stats" style="
                    border-top: 1px solid #0f3460;
                    padding-top: 10px;
                    margin-top: 10px;
                    font-size: 11px;
                    color: #aaa;
                ">
                    <div>Players: <span id="stat-players" style="color: #4fbdba;">--</span></div>
                    <div>Planets: <span id="stat-planets" style="color: #4fbdba;">--</span></div>
                    <div>Fleets: <span id="stat-fleets" style="color: #4fbdba;">--</span></div>
                </div>
                <div id="turn-progress" style="
                    margin-top: 10px;
                    height: 4px;
                    background: #0f3460;
                    border-radius: 2px;
                    overflow: hidden;
                ">
                    <div id="progress-bar" style="
                        height: 100%;
                        background: linear-gradient(90deg, #4fbdba, #7ec8e3);
                        width: 0%;
                        transition: width 1s linear;
                    "></div>
                </div>
                <div id="notification-area" style="
                    margin-top: 10px;
                    font-size: 10px;
                    color: #ffeb3b;
                    text-align: center;
                    height: 15px;
                "></div>
            </div>
        `;
        
        // Add to page
        document.body.insertAdjacentHTML('beforeend', timerHtml);
    }
    
    updateTurnStatus() {
        fetch('get_turn_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.nextTurnTime = data.next_turn_time;
                    
                    // Check if turn just changed
                    if (this.currentTurn > 0 && data.current_turn > this.currentTurn) {
                        this.showTurnNotification(data.current_turn);
                    }
                    
                    this.currentTurn = data.current_turn;
                    
                    // Update display
                    document.getElementById('current-turn').textContent = data.current_turn || '0';
                    document.getElementById('stat-players').textContent = data.active_players || '0';
                    
                    if (data.stats) {
                        document.getElementById('stat-planets').textContent = data.stats.total_planets || '0';
                        document.getElementById('stat-fleets').textContent = data.stats.total_fleets || '0';
                    }
                }
            })
            .catch(error => {
                console.error('Failed to fetch turn status:', error);
            });
    }
    
    updateCountdown() {
        if (!this.nextTurnTime) return;
        
        const now = Math.floor(Date.now() / 1000);
        const secondsLeft = Math.max(0, this.nextTurnTime - now);
        
        // Format time
        const minutes = Math.floor(secondsLeft / 60);
        const seconds = secondsLeft % 60;
        const timeString = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        // Update countdown display
        const countdownEl = document.getElementById('countdown');
        if (countdownEl) {
            countdownEl.textContent = timeString;
            
            // Flash when less than 10 seconds
            if (secondsLeft < 10) {
                countdownEl.style.color = '#ff6b6b';
                countdownEl.style.animation = 'pulse 1s infinite';
            } else if (secondsLeft < 30) {
                countdownEl.style.color = '#ffd93d';
                countdownEl.style.animation = 'none';
            } else {
                countdownEl.style.color = '#7ec8e3';
                countdownEl.style.animation = 'none';
            }
        }
        
        // Update progress bar
        const progress = ((300 - secondsLeft) / 300) * 100;
        const progressBar = document.getElementById('progress-bar');
        if (progressBar) {
            progressBar.style.width = progress + '%';
        }
        
        // Show notifications at specific times
        const notificationArea = document.getElementById('notification-area');
        if (notificationArea) {
            if (secondsLeft === 60) {
                notificationArea.textContent = '⚠️ 1 minute until next turn!';
                this.playNotificationSound();
            } else if (secondsLeft === 30) {
                notificationArea.textContent = '⚠️ 30 seconds until next turn!';
            } else if (secondsLeft === 10) {
                notificationArea.textContent = '🚨 Turn processing soon!';
                this.playNotificationSound();
            } else if (secondsLeft === 0) {
                notificationArea.textContent = '🔄 Processing turn...';
                // Refresh turn status after a short delay
                setTimeout(() => this.updateTurnStatus(), 2000);
            } else if (secondsLeft > 60) {
                notificationArea.textContent = '';
            }
        }
    }
    
    showTurnNotification(turnNumber) {
        // Create a temporary notification
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, #f39c12, #e74c3c);
            color: white;
            padding: 20px 40px;
            border-radius: 10px;
            font-size: 24px;
            font-weight: bold;
            z-index: 100000;
            animation: slideIn 0.5s ease-out;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        `;
        notification.textContent = `⚔️ TURN ${turnNumber} COMPLETE! ⚔️`;
        
        document.body.appendChild(notification);
        
        // Play sound
        this.playNotificationSound();
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.5s ease-out';
            setTimeout(() => notification.remove(), 500);
        }, 3000);
    }
    
    playNotificationSound() {
        // Create a simple beep sound using Web Audio API
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            gainNode.gain.value = 0.1;
            
            oscillator.start();
            oscillator.stop(audioContext.currentTime + 0.1);
        } catch (e) {
            // Silent fail if audio not supported
        }
    }
    
    destroy() {
        if (this.timerInterval) clearInterval(this.timerInterval);
        if (this.updateInterval) clearInterval(this.updateInterval);
        
        const timer = document.getElementById('turn-timer');
        if (timer) timer.remove();
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    
    @keyframes slideIn {
        from {
            transform: translate(-50%, -50%) scale(0.5);
            opacity: 0;
        }
        to {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }
    }
    
    @keyframes fadeOut {
        from {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }
        to {
            opacity: 0;
            transform: translate(-50%, -50%) scale(0.8);
        }
    }
`;
document.head.appendChild(style);

// Initialize when page loads
let turnTimer = null;
document.addEventListener('DOMContentLoaded', function() {
    turnTimer = new TurnTimer();
    turnTimer.init();
});