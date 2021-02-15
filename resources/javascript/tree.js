'use strict';

class SimpleTree extends Emitter {
    constructor (parent, properties = {}) {
        super();
        // do not toggle with click
        parent.addEventListener('click', e => {
            // if (e && e.)


            // e.clientX to prevent stopping Enter key
            // e.detail to prevent dbl-click
            // e.offsetX to allow plus and minus clicking
            if (e && e.clientX && e.detail === 1 && e.offsetX >= 0) {
                // if (e.target.getAttribute('href') !== '#')

                return e.preventDefault();
            }

            const active = this.active();
            if (active && active.dataset.type === SimpleTree.FILE) {

                e.preventDefault();
                this.emit('action', active);
                if (properties['no-focus-on-action'] === true) {
                    window.clearTimeout(this.id);
                }
            }

        });
        parent.classList.add('simple-tree');
        if (properties.dark) {
            parent.classList.add('dark');
        }
        this.parent = parent.appendChild(document.createElement('details'));
        this.parent.appendChild(document.createElement('summary'));
        this.parent.open = true;
        // use this function to alter a node before being passed to this.file or this.folder
        this.interrupt = node => node;
    }
    append(element, parent, before, callback = () => { }) {
        if (before) {
            parent.insertBefore(element, before);
        }
        else {
            parent.appendChild(element);
        }
        callback();
        return element;
    }
    file(node, parent = this.parent, before) {
        parent = parent.closest('details');
        node = this.interrupt(node);
        HTMLAnchorElement
        const a = this.append(Object.assign(document.createElement('a'), {
            textContent: node.name,
            href: node.link || '#',
            className: 'text-reset',
        }), parent, before);
        a.dataset.type = SimpleTree.FILE;
        this.emit('created', a, node);
        return a;
    }
    folder(node, parent = this.parent, before) {
        parent = parent.closest('details');
        node = this.interrupt(node);
        const details = document.createElement('details');
        const summary = Object.assign(document.createElement('summary'), {
            textContent: node.name
        });

        if (node.link && node.link !== '#') {
            summary.dataset.href = node.link;
            summary.innerHTML = `<a href="${node.link}" class="text-reset summary">${node.name}</a>`;
        }

        details.appendChild(summary);
        this.append(details, parent, before, () => {
            details.open = node.open;
            details.dataset.type = SimpleTree.FOLDER;
        });
        this.emit('created', summary, node);
        return summary;
    }
    open(details) {
        details.open = true;
    }
    hierarchy(element = this.active()) {
        if (this.parent.contains(element)) {
            const list = [];
            while (element !== this.parent) {
                if (element.dataset.type === SimpleTree.FILE) {
                    list.push(element);
                }
                else if (element.dataset.type === SimpleTree.FOLDER) {
                    list.push(element.querySelector('summary'));
                }
                element = element.parentElement;
            }
            return list;
        }
        else {
            return [];
        }
    }
    siblings(element = this.parent.querySelector('a, details')) {
        if (this.parent.contains(element)) {
            if (element.dataset.type === undefined) {
                element = element.parentElement;
            }
            return [...element.parentElement.children].filter(e => {
                return e.dataset.type === SimpleTree.FILE || e.dataset.type === SimpleTree.FOLDER;
            }).map(e => {
                if (e.dataset.type === SimpleTree.FILE) {
                    return e;
                }
                else {
                    return e.querySelector('summary');
                }
            });
        }
        else {
            return [];
        }
    }
    children(details) {
        const e = details.querySelector('a, details');
        if (e) {
            return this.siblings(e);
        }
        else {
            return [];
        }
    }
}
SimpleTree.FILE = 'file';
SimpleTree.FOLDER = 'folder';

class SelectTree extends SimpleTree {
    constructor (parent, options = {}) {
        super(parent, options);
        /* multiple clicks outside of elements */
        parent.addEventListener('click', e => {
            if (e.detail > 1) {
                const active = this.active();
                if (active && active !== e.target) {
                    if (e.target.tagName === 'A' || e.target.tagName === 'SUMMARY') {
                        return this.select(e.target, 'click');
                    }
                }
                if (active) {
                    this.focus(active);
                }
            }
        });
        window.addEventListener('focus', () => {
            const active = this.active();
            if (active) {
                this.focus(active);
            }
        });
        parent.addEventListener('focusin', e => {
            const active = this.active();
            if (active !== e.target) {
                this.select(e.target, 'focus');
            }
        });
        this.on('created', (element, node) => {

            if (node.selected) {
                this.select(element);
            }
        });
        parent.classList.add('select-tree');
        // navigate
        if (options.navigate) {
            this.parent.addEventListener('keydown', e => {
                const { code } = e;
                if (code === 'ArrowUp' || code === 'ArrowDown') {
                    this.navigate(code === 'ArrowUp' ? 'backward' : 'forward');
                    e.preventDefault();
                }
            });
        }
    }
    focus(target) {
        window.clearTimeout(this.id);
        this.id = window.setTimeout(() => document.hasFocus() && target.focus(), 100);
    }
    select(target) {
        const summary = target.querySelector('summary');

        if (summary) {
            target = summary;
        }

        [...this.parent.querySelectorAll('.selected')].forEach(e => e.classList.remove('selected'));

        target.classList.add('selected');
        // target.querySelector('a').classList.add('selected');

        this.focus(target);
        this.emit('select', target);
    }
    active() {
        return this.parent.querySelector('.selected');
    }
    navigate(direction = 'forward') {
        const e = this.active();
        if (e) {
            const list = [...this.parent.querySelectorAll('a, summary')];
            const index = list.indexOf(e);
            const candidates = direction === 'forward' ? list.slice(index + 1) : list.slice(0, index).reverse();
            for (const m of candidates) {
                if (m.getBoundingClientRect().height) {
                    return this.select(m);
                }
            }
        }
    }
}

class JSONTree extends SelectTree {
    json(array, parent) {
        array.forEach(item => {
            if (item.type === SimpleTree.FOLDER) {
                const folder = this['folder'](item, parent);
                if (item.children) {
                    this.json(item.children, folder);
                }
            }
            else {
                this.file(item, parent);
            }
        });
    }
}

window.Tree = JSONTree;
