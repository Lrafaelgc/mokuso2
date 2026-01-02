let timerId = null;
let interval = 250;
self.onmessage = function(e) {
    if (e.data.command === 'START') {
        if (timerId) clearInterval(timerId);
        timerId = setInterval(() => self.postMessage({ type: 'TICK' }), interval);
    } else if (e.data.command === 'STOP') {
        if (timerId) clearInterval(timerId);
        timerId = null;
    }
};