'use strict';

const Emitter = typeof window.Emitter === 'undefined' ? class Emitter {
    constructor () {
        this.events = {};
    }
    on(name, callback) {
        this.events[name] = this.events[name] || [];
        this.events[name].push(callback);
    }
    once(name, callback) {
        callback.once = true;
        this.on(name, callback);
    }
    emit(name, ...data) {
        if (this.events[name] === undefined) {
            return;
        }
        for (const c of [...this.events[name]]) {
            c(...data);
            if (c.once) {
                const index = this.events[name].indexOf(c);
                this.events[name].splice(index, 1);
            }
        }
    }
} : window.Emitter;
