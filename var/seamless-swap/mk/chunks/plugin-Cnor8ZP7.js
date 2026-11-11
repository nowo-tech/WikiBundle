import { F as e, M as t, j as n, ot as r, st as i, ut as a } from "./lib-9QpRoHAM.js";
var o = /^[A-Za-z0-9_-]{1,64}$/, s = /^[A-Za-z0-9+/]{4,512}={0,2}$/, c = (e, t, n) => typeof e == "number" && Number.isInteger(e) && e >= t && e <= n, l = (e, t, n) => typeof e == "string" && e.length >= t && e.length <= n && !e.includes("\0"), u = (e) => e && typeof e == "object" && !Array.isArray(e) ? e : null;
function d(e) {
	let t = u(e);
	if (!t || t.v !== 1) return null;
	let n = u(t.quote), r = u(t.position), i = u(t.block);
	if (!n || !r || !i || !l(n.exact, 1, 4096) || !l(n.prefix, 0, 32) || !l(n.suffix, 0, 32) || !c(r.start, 0, 1e7) || !c(r.end, 0, 1e7) || r.start >= r.end || r.end - r.start !== n.exact.length || !c(i.index, 0, 1e5) || i.heading !== null && !l(i.heading, 1, 200)) return null;
	let a = null;
	if (t.yjs !== void 0 && t.yjs !== null) {
		let e = u(t.yjs);
		if (!e || typeof e.start != "string" || typeof e.end != "string" || !s.test(e.start) || !s.test(e.end)) return null;
		a = {
			start: e.start,
			end: e.end
		};
	}
	let o = {
		v: 1,
		quote: {
			exact: n.exact,
			prefix: n.prefix,
			suffix: n.suffix
		},
		position: {
			start: r.start,
			end: r.end
		},
		block: {
			index: i.index,
			heading: i.heading
		},
		yjs: a
	};
	return new TextEncoder().encode(JSON.stringify(o)).length <= 16384 ? o : null;
}
var f = class {
	text;
	segments;
	blocks;
	constructor(e) {
		let t = "", n = [], r = [], i = null, a = (e, r, i) => {
			n.push({
				offset: t.length,
				pos: r,
				length: e.length,
				text: i
			}), t += e;
		};
		e.descendants((n, o, s) => (s === e ? (t !== "" && !t.endsWith("\n") && a("\n", o, !1), n.type.name === "heading" && (i = n.textContent.trim().slice(0, 200) || i), r.push({
			offset: t.length,
			heading: i
		})) : n.isBlock && t !== "" && !t.endsWith("\n") && a("\n", o, !1), n.isText ? a(n.text ?? "", o, !0) : n.isLeaf && a("￼", o, !1), !0)), this.text = t, this.segments = n, this.blocks = r;
	}
	segmentAt(e) {
		let t = 0, n = this.segments.length - 1, r = null;
		for (; t <= n;) {
			let i = t + n >> 1, a = this.segments[i];
			a.offset <= e ? (r = a, t = i + 1) : n = i - 1;
		}
		return r;
	}
	posAt(e, t = !1) {
		let n = this.segmentAt(t ? e - 1 : e);
		return n ? n.text ? n.pos + Math.min(e - n.offset, n.length) : t ? n.pos + 1 : n.pos : 1;
	}
	offsetAt(e) {
		for (let t of this.segments) {
			if (t.text && e >= t.pos && e <= t.pos + t.length) return t.offset + (e - t.pos);
			if (!t.text && e <= t.pos) return t.offset;
			if (!t.text && e === t.pos + 1 && this.text[t.offset] === "￼") return t.offset + 1;
		}
		return this.text.length;
	}
	blockAt(e) {
		let t = 0, n = this.blocks.length - 1, r = 0;
		for (; t <= n;) {
			let i = t + n >> 1;
			this.blocks[i].offset <= e ? (r = i, t = i + 1) : n = i - 1;
		}
		return r;
	}
};
function p(e, t, n) {
	if (t >= n || n - t > 4096) return null;
	let { text: r } = e, i = e.blocks[e.blockAt(t)];
	return {
		v: 1,
		quote: {
			exact: r.slice(t, n),
			prefix: r.slice(Math.max(0, t - 32), t),
			suffix: r.slice(n, n + 32)
		},
		position: {
			start: t,
			end: n
		},
		block: {
			index: e.blockAt(t),
			heading: i?.heading ?? null
		},
		yjs: null
	};
}
function m(e, t) {
	let n = 0;
	for (; n < e.length && n < t.length && e[e.length - 1 - n] === t[t.length - 1 - n];) n++;
	return n;
}
function h(e, t) {
	let n = 0;
	for (; n < e.length && n < t.length && e[n] === t[n];) n++;
	return n;
}
function g(e, t) {
	let { text: n } = e, { exact: r, prefix: i, suffix: a } = t.quote, o = null, s = n.indexOf(r);
	for (let c = 0; s !== -1 && c < 1e3; c++) {
		let c = m(n.slice(Math.max(0, s - i.length), s), i) + h(n.slice(s + r.length, s + r.length + a.length), a), l = Math.abs(s - t.position.start);
		!o || c > o.score || c === o.score && l < o.distance ? o = {
			start: s,
			score: c,
			distance: l,
			block: null
		} : c === o.score && l === o.distance && (o.block ??= e.blockAt(o.start) === t.block.index, !o.block && e.blockAt(s) === t.block.index && (o = {
			start: s,
			score: c,
			distance: l,
			block: !0
		})), s = n.indexOf(r, s + 1);
	}
	return o ? {
		start: o.start,
		end: o.start + r.length
	} : null;
}
// src/Resources/assets/src/comments/plugin.ts
var _ = 2e3, v = 2e3, y = /* @__PURE__ */ new Set([
	"code_block",
	"math_block",
	"math_inline"
]), b = 1500;
function x(e) {
	let t = C.getState(e), n = {
		open: 0,
		resolved: 0,
		orphaned: 0,
		page: 0,
		total: 0
	};
	if (!t) return n;
	for (let e of t.threads.values()) n.total++, e.resolved ? n.resolved++ : n.open++, e.anchor ? t.ranges.get(e.id) || n.orphaned++ : n.page++;
	return n;
}
var S = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})$/, C = new i("nowo-milkdown-comments");
function w(e) {
	let t = /* @__PURE__ */ new Map();
	if (!Array.isArray(e)) return t;
	e.length > 2e3 && console.warn(`nowo-milkdown: only the first ${_} comment threads are shown`);
	for (let n of e.slice(0, _)) {
		let e = T(n);
		e && !t.has(e.id) && t.set(e.id, e);
	}
	return t;
}
function T(e) {
	if (!e || typeof e != "object") return null;
	let t = e;
	if (typeof t.id != "string" || !o.test(t.id)) return null;
	let n = t.anchor === null || t.anchor === void 0 ? null : d(t.anchor);
	if (n === null && t.anchor !== null && t.anchor !== void 0) return null;
	let r = typeof t.count == "number" && Number.isInteger(t.count) && t.count >= 0 ? Math.min(t.count, 1e6) : 0, i = {
		id: t.id,
		anchor: n,
		resolved: t.resolved === !0,
		count: r
	};
	return typeof t.label == "string" && t.label !== "" && (i.label = t.label.slice(0, 200)), typeof t.updatedAt == "string" && S.test(t.updatedAt) && (i.updatedAt = t.updatedAt), i;
}
function E(e, r) {
	let i = [];
	for (let [e, t] of r.ranges) {
		let a = r.threads.get(e);
		if (!t || !a || a.resolved && !r.showResolved) continue;
		let o = ["nowo-milkdown-comment"];
		a.resolved && o.push("nowo-milkdown-comment--resolved"), r.active === e && o.push("nowo-milkdown-comment--active"), r.flash === e && o.push("nowo-milkdown-comment--flash"), i.push(n.inline(t.from, t.to, {
			class: o.join(" "),
			"data-comment-id": e
		}, { id: e }));
	}
	return t.create(e, i);
}
function D(e) {
	let t = C.getState(e);
	if (!t) return [];
	let n = [];
	for (let [e, r] of t.ranges) {
		let i = t.threads.get(e);
		r && i && (!i.resolved || t.showResolved) && n.push({
			id: e,
			range: r
		});
	}
	return n.sort((e, t) => e.range.from - t.range.from || e.range.to - t.range.to);
}
function O(e, t, n) {
	let r = !1;
	e.doc.nodesBetween(t, n, (e) => (y.has(e.type.name) && (r = !0), !r));
	let i = e.doc.resolve(t);
	for (let e = i.depth; e > 0; e--) if (y.has(i.node(e).type.name)) return !0;
	return r;
}
function k(e, t, n, r) {
	let i = n.anchor;
	if (!i) return null;
	if (r && i.yjs) {
		let t = r.decode(i.yjs.start), n = r.decode(i.yjs.end);
		if (t !== null && n !== null && t < n && n <= e.doc.content.size) return {
			from: t,
			to: n
		};
	}
	let a = g(t, i);
	return a ? {
		from: t.posAt(a.start),
		to: t.posAt(a.end, !0)
	} : null;
}
function A(e, t, n, r, i = new f(e.doc)) {
	if (t >= n || O(e, t, n)) return null;
	let a = p(i, i.offsetAt(t), i.offsetAt(n));
	if (!a || a.quote.exact.trim() === "") return null;
	if (r) {
		let e = r.encode(t, 0), i = r.encode(n, -1);
		e && i && (a.yjs = {
			start: e,
			end: i
		});
	}
	return a;
}
var j = (e, t) => e.quote.exact === t.quote.exact && e.quote.prefix === t.quote.prefix && e.quote.suffix === t.quote.suffix && e.position.start === t.position.start && e.block.index === t.block.index;
function M(e, t, n, r = !1) {
	return e.dispatchEvent(new CustomEvent(`nowo-milkdown:${t}`, {
		bubbles: !0,
		cancelable: r,
		detail: n
	}));
}
function N(e, t) {
	e.dispatch(e.state.tr.setMeta(C, t).setMeta("addToHistory", !1));
}
var P = class {
	view;
	host;
	resolveTimer = null;
	refreshTimer = null;
	orphaned = /* @__PURE__ */ new Set();
	drifted = /* @__PURE__ */ new Map();
	lastCounts = "";
	constructor(e, t) {
		this.view = e, this.host = t;
	}
	update(e, t) {
		let n = C.getState(e.state), r = C.getState(t);
		n && (n.threads === r?.threads ? e.state.doc.eq(t.doc) || this.schedule(300) : this.schedule(0));
	}
	schedule(e) {
		this.resolveTimer && clearTimeout(this.resolveTimer), this.resolveTimer = setTimeout(() => this.resolveNow(), e);
	}
	resolveNow() {
		this.resolveTimer && clearTimeout(this.resolveTimer), this.resolveTimer = null;
		let e = this.view.state, t = C.getState(e);
		if (!t) return;
		let n = new f(e.doc), r = this.host.yjs(), i = /* @__PURE__ */ new Map(), a = [], o = [];
		for (let s of t.threads.values()) {
			let t = k(e, n, s, r);
			if (i.set(s.id, t), s.anchor && (!t && !this.orphaned.has(s.id) ? (this.orphaned.add(s.id), a.push(s.id)) : t && this.orphaned.delete(s.id) && o.push(s.id), t)) {
				let i = A(e, t.from, t.to, r, n);
				i && (!j(i, s.anchor) || r !== null && s.anchor.yjs === null && i.yjs !== null) && this.drifted.set(s.id, i);
			}
		}
		for (let e of this.orphaned) t.threads.has(e) || this.orphaned.delete(e);
		N(this.view, { ranges: i }), (a.length || o.length) && M(this.host.root, "comment-orphaned", {
			orphaned: a,
			attached: o
		});
		let s = x(this.view.state), c = JSON.stringify(s);
		c !== this.lastCounts && (this.lastCounts = c, M(this.host.root, "comments-count", s)), this.drifted.size && !this.refreshTimer && (this.refreshTimer = setTimeout(() => this.flushDrift(), v));
	}
	flushDrift() {
		this.refreshTimer = null;
		let e = C.getState(this.view.state);
		if (!e || !this.drifted.size) return;
		let t = [], n = new Map(e.threads);
		for (let [e, r] of this.drifted) {
			let i = n.get(e);
			i && !this.orphaned.has(e) && (n.set(e, {
				...i,
				anchor: r
			}), t.push({
				id: e,
				anchor: r
			}));
		}
		this.drifted.clear(), t.length && (N(this.view, { threads: n }), M(this.host.root, "comment-anchors-changed", { anchors: t }));
	}
	status(e) {
		let t = C.getState(this.view.state), n = t?.threads.get(e);
		return !t || !n ? "unknown" : n.anchor ? t.ranges.get(e) ? "attached" : "orphaned" : "page";
	}
	destroy() {
		this.resolveTimer && clearTimeout(this.resolveTimer), this.refreshTimer && clearTimeout(this.refreshTimer);
	}
}, F = /* @__PURE__ */ new WeakMap(), I = (e) => F.get(e);
function L(e, t, n) {
	let r = D(e.state);
	if (!r.length) return !1;
	let i = C.getState(e.state), a = r.findIndex((e) => e.id === i.active), o = e.state.selection.from, s;
	if (a !== -1) s = (a + n + r.length) % r.length;
	else if (n === 1) s = Math.max(0, r.findIndex((e) => e.range.from >= o));
	else {
		let e = r.filter((e) => e.range.from < o);
		s = e.length ? r.indexOf(e[e.length - 1]) : r.length - 1;
	}
	return R(e, t, r[s].id, r);
}
function R(e, t, n, r = D(e.state)) {
	let i = r.findIndex((e) => e.id === n);
	if (i === -1) return !1;
	let { range: o } = r[i], s = C.getState(e.state).threads.get(n);
	return e.dispatch(e.state.tr.setSelection(a.create(e.state.doc, o.from, o.to)).setMeta(C, { active: n }).setMeta("addToHistory", !1).scrollIntoView()), e.focus(), t.announce(s.label ? "comments.position_label" : "comments.position", {
		index: i + 1,
		count: r.length,
		label: s.label ?? ""
	}), !0;
}
function z(e, t) {
	let n = C.getState(e.state), r = n?.ranges.get(t);
	if (!n || !r) return !1;
	N(e, {
		active: t,
		flash: t
	});
	let i = e.domAtPos(r.from), a = i.node instanceof Element ? i.node : i.node.parentElement;
	return (e.dom.querySelector(`[data-comment-id="${CSS.escape(t)}"]`) ?? a)?.scrollIntoView({ block: "center" }), setTimeout(() => {
		!e.isDestroyed && C.getState(e.state)?.flash === t && N(e, { flash: null });
	}, b), !0;
}
function B(e, t) {
	if (!t.canCreate) return null;
	let { from: n, to: r } = e.state.selection, i = A(e.state, n, r, t.yjs());
	if (!i) return t.announce("comments.no_selection", {}), null;
	let a = {
		anchor: i,
		text: i.quote.exact
	}, o = !M(t.root, "comment-create", a, !0);
	return {
		...a,
		cancelled: o
	};
}
function V(e, t, n) {
	M(e.root, "comment-open", { id: t }, !0) && n?.(t);
}
function H(n, i) {
	return [new r({
		key: C,
		state: {
			init: (e, t) => {
				let n = {
					threads: /* @__PURE__ */ new Map(),
					ranges: /* @__PURE__ */ new Map(),
					active: null,
					flash: null,
					showResolved: !1
				};
				return {
					...n,
					decorations: E(t.doc, n)
				};
			},
			apply(e, t, n, r) {
				let i = e.getMeta(C), a = t.ranges;
				if (e.docChanged) {
					a = /* @__PURE__ */ new Map();
					for (let [n, r] of t.ranges) {
						if (!r) {
							a.set(n, null);
							continue;
						}
						let t = e.mapping.map(r.from, 1), i = e.mapping.map(r.to, -1);
						a.set(n, t < i ? {
							from: t,
							to: i
						} : null);
					}
				}
				if (!i && !e.docChanged) return t;
				let o = {
					threads: i?.threads ?? t.threads,
					ranges: i?.ranges ?? a,
					active: i && "active" in i ? i.active ?? null : t.active,
					flash: i && "flash" in i ? i.flash ?? null : t.flash,
					showResolved: i?.showResolved ?? t.showResolved
				};
				return o.active && !o.ranges.get(o.active) && (o.active = null), {
					...o,
					decorations: E(r.doc, o)
				};
			}
		},
		props: {
			decorations: (e) => C.getState(e)?.decorations ?? t.empty,
			handleClick(e, t, r) {
				let a = (r.target?.closest?.("[data-comment-id]"))?.getAttribute("data-comment-id");
				return !a || !C.getState(e.state)?.threads.has(a) || (N(e, { active: a }), V(n, a, i.open)), !1;
			}
		},
		view(e) {
			let t = new P(e, n);
			return F.set(e, t), t;
		}
	}), e({
		"Mod-Alt-m": (e, t, r) => {
			if (!r) return !1;
			let a = B(r, n);
			return a && !a.cancelled && i.create?.(a), a !== null || !r.state.selection.empty;
		},
		"Mod-Alt-PageDown": (e, t, r) => r ? L(r, n, 1) : !1,
		"Mod-Alt-PageUp": (e, t, r) => r ? L(r, n, -1) : !1,
		Enter: (e, t, r) => {
			let a = C.getState(e), o = a?.active ? a.ranges.get(a.active) : null;
			if (!r || !a?.active || !o) return !1;
			let { from: s, to: c } = e.selection;
			return s !== o.from || c !== o.to ? !1 : (V(n, a.active, i.open), !0);
		},
		Escape: (e, t, n) => !n || !C.getState(e)?.active ? !1 : (N(n, { active: null }), !0)
	})];
}
export { o as _, x as a, D as c, A as d, z as f, f as g, L as h, I as i, T as l, B as m, C as n, M as o, N as p, H as r, R as s, S as t, w as u, d as v };
