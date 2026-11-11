import { $ as e, Ot as t, St as n, at as r, ct as i, et as a, nt as o, ot as s, st as c, ut as l } from "./lib-9QpRoHAM.js";
import { u } from "./lib-DaU_YkmU.js";
import { i as d } from "./dist-BmAqUYId.js";
import { d as f, l as p, r as m, u as h } from "./directives-Cjq-r_Gz.js";
import { a as g } from "./runtime-DBQRQGPT.js";
import { a as _, i as v, n as y, r as b, t as x } from "./layer-N-kUiX52.js";
// src/Resources/assets/src/commands/blocks.ts
function S(e) {
	let { selection: t } = e;
	if (t instanceof r && t.$from.depth === 0) return {
		pos: t.from,
		node: t.node,
		depth: 1
	};
	let n = t.$from;
	for (let e = n.depth; e > 0; e--) if (n.node(e).type.name === "list_item") return {
		pos: n.before(e),
		node: n.node(e),
		depth: e
	};
	return n.depth < 1 ? null : {
		pos: n.before(1),
		node: n.node(1),
		depth: 1
	};
}
var C = [
	"text",
	"h1",
	"h2",
	"h3",
	"bullet_list",
	"ordered_list",
	"task_list",
	"quote",
	"code"
];
function w(t) {
	return (n, r) => {
		let i = n.schema.nodes;
		switch (t) {
			case "text": return a(i.paragraph)(n, r) ? !0 : e(n, r);
			case "h1":
			case "h2":
			case "h3": return a(i.heading, { level: Number(t.slice(1)) })(n, r);
			case "code": return a(i.code_block, { language: "" })(n, r);
			case "quote": return o(i.blockquote)(n, r);
			case "bullet_list": return d(i.bullet_list)(n, r);
			case "ordered_list": return d(i.ordered_list)(n, r);
			case "task_list": return d(i.bullet_list)(n, (e) => {
				let t = e.selection.$from;
				for (let n = t.depth; n > 0; n--) {
					let r = t.node(n);
					if (r.type.name === "list_item") {
						e.setNodeMarkup(t.before(n), void 0, {
							...r.attrs,
							checked: !1
						});
						break;
					}
				}
				r?.(e);
			});
		}
	};
}
function T(e, t) {
	let n = e.doc.resolve(Math.min(t + 1, e.doc.content.size));
	return e.setSelection(l.near(n));
}
function E(e, t) {
	let n = S(e);
	if (!n) return !1;
	if (t) {
		let r = n.pos + n.node.nodeSize;
		t(T(e.tr.insert(r, n.node.copy(n.node.content)), r).scrollIntoView());
	}
	return !0;
}
function D(e, t) {
	let n = S(e);
	if (!n) return !1;
	if (t) {
		let r = e.tr, a = r.doc.resolve(n.pos).parent;
		if (a.childCount === 1 && n.depth === 1) r.replaceWith(n.pos, n.pos + n.node.nodeSize, e.schema.nodes.paragraph.create());
		else if (a.childCount === 1) {
			let e = r.doc.resolve(n.pos);
			r.delete(e.before(), e.after());
		} else r.delete(n.pos, n.pos + n.node.nodeSize);
		let o = r.doc.resolve(Math.min(n.pos, r.doc.content.size));
		t(r.setSelection(i.near(o, -1)).scrollIntoView());
	}
	return !0;
}
function O(e) {
	return (t, r) => {
		let i = S(t);
		if (!i) return !1;
		let a = t.doc.resolve(i.pos), o = a.index(), s = a.parent, c = o + e;
		if (c < 0 || c >= s.childCount) return !1;
		if (r) {
			let a = s.child(c), o = t.selection.from - i.pos, u = e === -1 ? i.pos - a.nodeSize : i.pos, d = e === -1 ? i.pos + i.node.nodeSize : i.pos + i.node.nodeSize + a.nodeSize, f = e === -1 ? n.from([i.node, a]) : n.from([a, i.node]), p = t.tr.replaceWith(u, d, f), m = e === -1 ? u : u + a.nodeSize, h = p.doc.resolve(Math.min(m + o, p.doc.content.size));
			r(p.setSelection(l.near(h)).scrollIntoView());
		}
		return !0;
	};
}
function k(e, r) {
	return (i, a) => {
		let o = e.replace(/\r\n?/g, "\n").split("\n");
		if (a) {
			if (o.length === 1) a(i.tr.insertText(o[0]).scrollIntoView());
			else {
				let e = o.map((e) => r.nodes.paragraph.create(null, e ? r.text(e) : null));
				a(i.tr.replaceSelection(new t(n.from(e), 1, 1)).scrollIntoView());
			}
		}
		return !0;
	};
}
function A(e) {
	let t = e.state, n = t.selection.getBookmark();
	return (r) => {
		e.state.doc === t.doc && (e.state.selection.eq(t.selection) || e.dispatch(e.state.tr.setSelection(n.resolve(e.state.doc))), r(e.state, e.dispatch), e.focus());
	};
}
// src/Resources/assets/src/ui/menu.ts
var j = 0, M = {
	Control: "Ctrl",
	Meta: "⌘",
	Shift: "Shift",
	Alt: "Alt",
	ArrowUp: "↑",
	ArrowDown: "↓"
}, N = (e) => e.split("+").map((e) => M[e] ?? e).join("+"), P = class e {
	host;
	onClose;
	parent;
	element;
	buttons = [];
	items = [];
	child = null;
	icons = !1;
	iconColumn = !1;
	typed = "";
	typedAt = 0;
	onOutside = (e) => {
		this.contains(e.target) || this.closeAll(!1);
	};
	constructor(e, t, n, r = null) {
		this.host = e, this.onClose = n, this.parent = r, this.element = document.createElement("div"), this.element.id = `nowo-milkdown-menu-${++j}`, this.element.className = "nowo-milkdown-menu", this.element.setAttribute("role", "menu"), this.element.setAttribute("aria-label", t), this.element.tabIndex = -1, this.element.hidden = !0, this.element.addEventListener("keydown", (e) => this.onKey(e)), this.element.addEventListener("click", (e) => {
			let t = e.target.closest(".nowo-milkdown-menu__item");
			t && this.element.contains(t) && this.activate(this.buttons.indexOf(t));
		}), this.element.addEventListener("mousemove", (e) => {
			let t = e.target.closest(".nowo-milkdown-menu__item"), n = t ? this.buttons.indexOf(t) : -1;
			n >= 0 && document.activeElement !== t && this.focus(n);
		});
	}
	get isOpen() {
		return !this.element.hidden;
	}
	open(e, t, n, r) {
		this.closeChild(), this.element.replaceChildren(), this.items = [], this.buttons = [], this.iconColumn = this.icons && e.some((e) => e.some((e) => !e.swatch && (e.icon !== void 0 || _(e.id) !== void 0))), this.element.classList.toggle("nowo-milkdown-menu--icons", this.iconColumn);
		for (let t of e.filter((e) => e.length)) {
			if (this.items.length) {
				let e = document.createElement("div");
				e.setAttribute("role", "separator"), e.className = "nowo-milkdown-menu__separator", this.element.append(e);
			}
			for (let e of t) this.addItem(e);
		}
		b(this.element, this.host), y(this.element, t, n, r), this.parent || document.addEventListener("pointerdown", this.onOutside, !0), this.focus(0);
	}
	addItem(e) {
		if (e.separatorBefore && this.items.length) {
			let e = document.createElement("div");
			e.setAttribute("role", "separator"), e.className = "nowo-milkdown-menu__separator", this.element.append(e);
		}
		let t = document.createElement("button");
		if (t.type = "button", t.tabIndex = -1, t.className = "nowo-milkdown-menu__item", t.setAttribute("role", e.checked === void 0 ? "menuitem" : "menuitemradio"), e.checked !== void 0 && t.setAttribute("aria-checked", String(e.checked)), t.dataset.id = e.id, e.swatch) {
			let n = document.createElement("span");
			n.className = `nowo-milkdown-menu__swatch ${e.swatch}`, n.setAttribute("aria-hidden", "true"), n.textContent = "A", t.append(n);
		}
		if (this.iconColumn && !e.swatch) {
			let n = e.icon ?? _(e.id), r = n && v(n, "nowo-milkdown-menu__icon") || document.createElement("span");
			r instanceof HTMLSpanElement && (r.className = "nowo-milkdown-menu__icon", r.setAttribute("aria-hidden", "true")), t.append(r);
		}
		let n = document.createElement("span");
		if (n.className = "nowo-milkdown-menu__label", n.textContent = e.label, t.append(n), e.disabled && (t.setAttribute("aria-disabled", "true"), e.hint)) {
			let n = document.createElement("span");
			n.className = "nowo-milkdown-menu__hint", n.textContent = e.hint, t.append(n);
		}
		if (e.shortcut) {
			t.setAttribute("aria-keyshortcuts", e.shortcut);
			let n = document.createElement("span");
			n.className = "nowo-milkdown-menu__shortcut", n.setAttribute("aria-hidden", "true"), n.textContent = N(e.shortcut), t.append(n);
		}
		if (e.items?.length) {
			t.setAttribute("aria-haspopup", "menu"), t.setAttribute("aria-expanded", "false");
			let e = document.createElement("span");
			e.className = "nowo-milkdown-menu__arrow", e.setAttribute("aria-hidden", "true"), e.textContent = "›", t.append(e);
		}
		this.items.push(e), this.buttons.push(t), this.element.append(t);
	}
	focus(e) {
		if (!this.buttons.length) {
			this.element.focus();
			return;
		}
		let t = (e + this.buttons.length) % this.buttons.length;
		this.buttons[t].focus();
	}
	current() {
		return this.buttons.indexOf(document.activeElement);
	}
	onKey(e) {
		if (e.ctrlKey || e.metaKey || e.altKey) return;
		let t = this.current(), n = !0;
		switch (e.key) {
			case "ArrowDown":
				this.focus(t + 1);
				break;
			case "ArrowUp":
				this.focus(t < 0 ? -1 : t - 1);
				break;
			case "Home":
				this.focus(0);
				break;
			case "End":
				this.focus(-1);
				break;
			case "ArrowRight":
				t >= 0 && this.items[t]?.items?.length && !this.items[t]?.disabled && this.openChild(t);
				break;
			case "ArrowLeft":
				this.parent ? this.parent.closeChild(!0) : n = !1;
				break;
			case "Escape":
				this.parent ? this.parent.closeChild(!0) : this.closeAll(!0);
				break;
			case "Tab":
				this.root().closeAll(!0);
				break;
			case "Enter":
			case " ":
				t >= 0 && this.activate(t);
				break;
			default: n = e.key.length === 1 && this.typeahead(e.key, t);
		}
		n && (e.preventDefault(), e.stopPropagation());
	}
	typeahead(e, t) {
		let n = Date.now();
		this.typed = n - this.typedAt < 700 ? this.typed + e.toLowerCase() : e.toLowerCase(), this.typedAt = n;
		let r = this.items.length;
		for (let e = this.typed.length > 1 ? 0 : 1; e <= r; e++) {
			let n = (Math.max(t, 0) + e) % r;
			if (this.items[n].label.toLowerCase().startsWith(this.typed)) return this.focus(n), !0;
		}
		return !0;
	}
	activate(e) {
		let t = this.items[e];
		if (!t || t.disabled) return;
		if (t.items?.length) {
			this.openChild(e);
			return;
		}
		let n = t.run;
		this.root().closeAll(!0), n?.();
	}
	openChild(t) {
		this.closeChild();
		let n = this.items[t], r = this.buttons[t], i = new e(this.host, n.label, () => void 0, this);
		i.icons = this.icons, this.child = i, r.setAttribute("aria-expanded", "true"), r.setAttribute("aria-controls", i.element.id);
		let a = r.getBoundingClientRect();
		i.open([n.items], a.right, a.top, this.element.getBoundingClientRect().left);
	}
	closeChild(e = !1) {
		if (!this.child) return;
		let t = this.child;
		this.child = null, t.destroy();
		let n = this.buttons.find((e) => e.getAttribute("aria-expanded") === "true");
		n?.setAttribute("aria-expanded", "false"), n?.removeAttribute("aria-controls"), e && n?.focus();
	}
	root() {
		return this.parent ? this.parent.root() : this;
	}
	contains(e) {
		return this.element.contains(e) || (this.child?.contains(e) ?? !1);
	}
	closeAll(e) {
		this.element.hidden || (this.closeChild(), x(this.element), document.removeEventListener("pointerdown", this.onOutside, !0), this.onClose(e));
	}
	destroy() {
		this.closeChild(), document.removeEventListener("pointerdown", this.onOutside, !0), x(this.element), this.element.remove();
	}
};
// src/Resources/assets/src/commands/colors.ts
function F(e, t) {
	let { from: n, to: r } = e.selection, i = null;
	return e.doc.nodesBetween(n, r, (e) => {
		if (i) return !1;
		let n = e.marks.find((e) => e.type === t && e.attrs.name === "mark");
		return n && (i = n), !0;
	}), i ? h(i.attrs.attributes) : {};
}
function I(e) {
	let t = e.selection.$from;
	if (t.depth < 1) return null;
	let n = t.node(1), r = n.type.name === "containerDirective" && n.attrs.name === "align";
	return {
		pos: t.before(1),
		node: n,
		container: r
	};
}
function L(e, t) {
	let n = e.schema.marks.textDirective, r = t && n ? F(e, n) : (() => {
		let t = I(e);
		return t?.container ? h(t.node.attrs.attributes) : {};
	})();
	return {
		text: p(r.text),
		bg: p(r.bg) ?? (t ? p(r.color) : null)
	};
}
function R(e, t) {
	return (n, r) => {
		let i = n.schema.marks.textDirective, { from: a, to: o, empty: s } = n.selection;
		if (!i || s) return !1;
		if (r) {
			let s = n.tr;
			n.doc.nodesBetween(a, o, (n, r) => {
				if (!n.isText) return !0;
				let c = n.marks.filter((e) => e.type === i);
				if (c.some((e) => e.attrs.name !== "mark")) return !1;
				let l = Math.max(a, r), u = Math.min(o, r + n.nodeSize), d = c.length ? h(c[0].attrs.attributes) : {};
				e === "bg" && delete d.color, t ? d[e] = t : delete d[e];
				for (let e of c) s.removeMark(l, u, e);
				return Object.keys(d).some((e) => e === "text" || e === "bg" || e === "color") && s.addMark(l, u, i.create({
					name: "mark",
					attributes: f(d)
				})), !1;
			}), r(s);
		}
		return !0;
	};
}
function z(e, t) {
	return (n, r) => {
		let i = I(n), a = n.schema.nodes.containerDirective;
		if (!i || !a) return !1;
		if (!r) return !0;
		let { pos: o, node: s, container: c } = i, u = n.selection.from - o;
		if (c) {
			let i = h(s.attrs.attributes);
			if (t ? i[e] = t : delete i[e], Object.keys(i).length) return r(n.tr.setNodeMarkup(o, void 0, {
				...s.attrs,
				attributes: f(i)
			})), !0;
			let a = n.tr.replaceWith(o, o + s.nodeSize, s.content);
			return r(a.setSelection(l.near(a.doc.resolve(Math.max(o + u - 1, 0)))).scrollIntoView()), !0;
		}
		if (!t) return !0;
		let d = a.create({
			name: "align",
			label: "",
			attributes: f({ [e]: t })
		}, s), p = n.tr.replaceWith(o, o + s.nodeSize, d);
		return r(p.setSelection(l.near(p.doc.resolve(Math.min(o + u + 1, p.doc.content.size)))).scrollIntoView()), !0;
	};
}
function B(e, t, n, r) {
	let i = L(e, n), a = n ? R : z, o = [];
	for (let e of ["text", "bg"]) {
		let n = e === "text" ? "text" : "background";
		o.push({
			id: `${e}_default`,
			label: g(t, `color.default_${n}`),
			checked: i[e] === null,
			separatorBefore: e === "bg",
			run: () => r(a(e, null))
		});
		for (let s of m.filter((e) => t.colors.length === 0 || t.colors.includes(e))) o.push({
			id: `${e}_${s}`,
			label: g(t, `color.${n}`, { color: g(t, `color.${s}`) }),
			swatch: e === "text" ? `nowo-milkdown-color--${s}` : `nowo-milkdown-bg--${s}`,
			checked: i[e] === s,
			run: () => r(a(e, s))
		});
	}
	return o;
}
var V = /* @__PURE__ */ new WeakMap();
function H(e, t) {
	let n = V.get(e);
	n || (n = new P(e.dom.closest(".nowo-milkdown") ?? document.body, g(t, "menu.color"), (t) => {
		t && e.focus();
	}), V.set(e, n));
	let r = A(e), i = e.coordsAtPos(e.state.selection.to);
	return n.open([B(e.state, t, !e.state.selection.empty, r)], i.left, i.bottom + 4), n;
}
var U = u(() => new s({
	key: new c("nowo-milkdown-color-menu"),
	view: () => ({ update: (e, t) => {
		let n = V.get(e);
		n?.isOpen && e.state.doc !== t.doc && n.closeAll(!1);
	} })
}));
export { E as a, A as c, B as colorItems, U as colorMenuPlugin, L as currentColors, D as i, w as l, C as n, k as o, H as openColorMenu, S as r, O as s, z as setBlockColor, R as setInlineColor, P as t };
