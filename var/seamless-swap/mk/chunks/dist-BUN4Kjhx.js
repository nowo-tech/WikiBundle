import { A as e, B as t, C as n, I as r, L as i, U as a, V as o, W as s, X as c, Y as l, a as u, c as d, g as f, i as p, o as m, r as h, s as g, z as _ } from "./code-block-DB02z2VD.js";
// node_modules/.pnpm/@lezer+common@1.5.5/node_modules/@lezer/common/dist/index.js
var ee = 1024, v = 0, y = class {
	constructor(e, t) {
		this.from = e, this.to = t;
	}
}, b = class {
	constructor(e = {}) {
		this.id = v++, this.perNode = !!e.perNode, this.deserialize = e.deserialize || (() => {
			throw Error("This node type doesn't define a deserialize function");
		}), this.combine = e.combine || null;
	}
	add(e) {
		if (this.perNode) throw RangeError("Can't add per-node props to node types");
		return typeof e != "function" && (e = C.match(e)), (t) => {
			let n = e(t);
			return n === void 0 ? null : [this, n];
		};
	}
};
b.closedBy = new b({ deserialize: (e) => e.split(" ") }), b.openedBy = new b({ deserialize: (e) => e.split(" ") }), b.group = new b({ deserialize: (e) => e.split(" ") }), b.isolate = new b({ deserialize: (e) => {
	if (e && e != "rtl" && e != "ltr" && e != "auto") throw RangeError("Invalid value for isolate: " + e);
	return e || "auto";
} }), b.contextHash = new b({ perNode: !0 }), b.lookAhead = new b({ perNode: !0 }), b.mounted = new b({ perNode: !0 });
var x = class {
	constructor(e, t, n, r = !1) {
		this.tree = e, this.overlay = t, this.parser = n, this.bracketed = r;
	}
	static get(e) {
		return e && e.props && e.props[b.mounted.id];
	}
}, S = Object.create(null), C = class e {
	constructor(e, t, n, r = 0) {
		this.name = e, this.props = t, this.id = n, this.flags = r;
	}
	static define(t) {
		let n = t.props && t.props.length ? Object.create(null) : S, r = +!!t.top | (t.skipped ? 2 : 0) | (t.error ? 4 : 0) | (t.name == null ? 8 : 0), i = new e(t.name || "", n, t.id, r);
		if (t.props) {
			for (let e of t.props) if (Array.isArray(e) || (e = e(i)), e) {
				if (e[0].perNode) throw RangeError("Can't store a per-node prop on a node type");
				n[e[0].id] = e[1];
			}
		}
		return i;
	}
	prop(e) {
		return this.props[e.id];
	}
	get isTop() {
		return (this.flags & 1) > 0;
	}
	get isSkipped() {
		return (this.flags & 2) > 0;
	}
	get isError() {
		return (this.flags & 4) > 0;
	}
	get isAnonymous() {
		return (this.flags & 8) > 0;
	}
	is(e) {
		if (typeof e == "string") {
			if (this.name == e) return !0;
			let t = this.prop(b.group);
			return t ? t.indexOf(e) > -1 : !1;
		}
		return this.id == e;
	}
	static match(e) {
		let t = Object.create(null);
		for (let n in e) for (let r of n.split(" ")) t[r] = e[n];
		return (e) => {
			for (let n = e.prop(b.group), r = -1; r < (n ? n.length : 0); r++) {
				let i = t[r < 0 ? e.name : n[r]];
				if (i) return i;
			}
		};
	}
};
C.none = new C("", Object.create(null), 0, 8);
var te = class e {
	constructor(e) {
		this.types = e;
		for (let t = 0; t < e.length; t++) if (e[t].id != t) throw RangeError("Node type ids should correspond to array positions when creating a node set");
	}
	extend(...t) {
		let n = [];
		for (let e of this.types) {
			let r = null;
			for (let n of t) {
				let t = n(e);
				if (t) {
					r ||= Object.assign({}, e.props);
					let n = t[1], i = t[0];
					i.combine && i.id in r && (n = i.combine(r[i.id], n)), r[i.id] = n;
				}
			}
			n.push(r ? new C(e.name, r, e.id, e.flags) : e);
		}
		return new e(n);
	}
}, w = /* @__PURE__ */ new WeakMap(), T = /* @__PURE__ */ new WeakMap(), E;
(function(e) {
	e[e.ExcludeBuffers = 1] = "ExcludeBuffers", e[e.IncludeAnonymous = 2] = "IncludeAnonymous", e[e.IgnoreMounts = 4] = "IgnoreMounts", e[e.IgnoreOverlays = 8] = "IgnoreOverlays", e[e.EnterBracketed = 16] = "EnterBracketed";
})(E ||= {});
var D = class e {
	constructor(e, t, n, r, i) {
		if (this.type = e, this.children = t, this.positions = n, this.length = r, this.props = null, i && i.length) {
			this.props = Object.create(null);
			for (let [e, t] of i) this.props[typeof e == "number" ? e : e.id] = t;
		}
	}
	toString() {
		let e = x.get(this);
		if (e && !e.overlay) return e.tree.toString();
		let t = "";
		for (let e of this.children) {
			let n = e.toString();
			n && (t && (t += ","), t += n);
		}
		return this.type.name ? (/\W/.test(this.type.name) && !this.type.isError ? JSON.stringify(this.type.name) : this.type.name) + (t.length ? "(" + t + ")" : "") : t;
	}
	cursor(e = 0) {
		return new de(this.topNode, e);
	}
	cursorAt(e, t = 0, n = 0) {
		let r = new de(w.get(this) || this.topNode);
		return r.moveTo(e, t), w.set(this, r._tree), r;
	}
	get topNode() {
		return new A(this, 0, 0, null);
	}
	resolve(e, t = 0) {
		let n = k(w.get(this) || this.topNode, e, t, !1);
		return w.set(this, n), n;
	}
	resolveInner(e, t = 0) {
		let n = k(T.get(this) || this.topNode, e, t, !0);
		return T.set(this, n), n;
	}
	resolveStack(e, t = 0) {
		return ue(this, e, t);
	}
	iterate(e) {
		let { enter: t, leave: n, from: r = 0, to: i = this.length } = e, a = e.mode || 0, o = (a & E.IncludeAnonymous) > 0;
		for (let e = this.cursor(a | E.IncludeAnonymous);;) {
			let a = !1;
			if (e.from <= i && e.to >= r && (!o && e.type.isAnonymous || t(e) !== !1)) {
				if (e.firstChild()) continue;
				a = !0;
			}
			for (; a && n && (o || !e.type.isAnonymous) && n(e), !e.nextSibling();) {
				if (!e.parent()) return;
				a = !0;
			}
		}
	}
	prop(e) {
		return e.perNode ? this.props ? this.props[e.id] : void 0 : this.type.prop(e);
	}
	get propValues() {
		let e = [];
		if (this.props) for (let t in this.props) e.push([+t, this.props[t]]);
		return e;
	}
	balance(t = {}) {
		return this.children.length <= 8 ? this : ge(C.none, this.children, this.positions, 0, this.children.length, 0, this.length, (t, n, r) => new e(this.type, t, n, r, this.propValues), t.makeTree || ((t, n, r) => new e(C.none, t, n, r)));
	}
	static build(e) {
		return pe(e);
	}
};
D.empty = new D(C.none, [], [], 0);
var ne = class e {
	constructor(e, t) {
		this.buffer = e, this.index = t;
	}
	get id() {
		return this.buffer[this.index - 4];
	}
	get start() {
		return this.buffer[this.index - 3];
	}
	get end() {
		return this.buffer[this.index - 2];
	}
	get size() {
		return this.buffer[this.index - 1];
	}
	get pos() {
		return this.index;
	}
	next() {
		this.index -= 4;
	}
	fork() {
		return new e(this.buffer, this.index);
	}
}, O = class e {
	constructor(e, t, n) {
		this.buffer = e, this.length = t, this.set = n;
	}
	get type() {
		return C.none;
	}
	toString() {
		let e = [];
		for (let t = 0; t < this.buffer.length;) e.push(this.childString(t)), t = this.buffer[t + 3];
		return e.join(",");
	}
	childString(e) {
		let t = this.buffer[e], n = this.buffer[e + 3], r = this.set.types[t], i = r.name;
		if (/\W/.test(i) && !r.isError && (i = JSON.stringify(i)), e += 4, n == e) return i;
		let a = [];
		for (; e < n;) a.push(this.childString(e)), e = this.buffer[e + 3];
		return i + "(" + a.join(",") + ")";
	}
	findChild(e, t, n, r, i) {
		let { buffer: a } = this, o = -1;
		for (let s = e; s != t && !(re(i, r, a[s + 1], a[s + 2]) && (o = s, n > 0)); s = a[s + 3]);
		return o;
	}
	slice(t, n, r) {
		let i = this.buffer, a = new Uint16Array(n - t), o = 0;
		for (let e = t, s = 0; e < n;) {
			a[s++] = i[e++], a[s++] = i[e++] - r;
			let n = a[s++] = i[e++] - r;
			a[s++] = i[e++] - t, o = Math.max(o, n);
		}
		return new e(a, o, this.set);
	}
};
function re(e, t, n, r) {
	switch (e) {
		case -2: return n < t;
		case -1: return r >= t && n < t;
		case 0: return n < t && r > t;
		case 1: return n <= t && r > t;
		case 2: return r > t;
		case 4: return !0;
	}
}
function k(e, t, n, r) {
	for (; e.from == e.to || (n < 1 ? e.from >= t : e.from > t) || (n > -1 ? e.to <= t : e.to < t);) {
		let t = !r && e instanceof A && e.index < 0 ? null : e.parent;
		if (!t) return e;
		e = t;
	}
	let i = r ? 0 : E.IgnoreOverlays;
	if (r) for (let r = e, a = r.parent; a; r = a, a = r.parent) r instanceof A && r.index < 0 && a.enter(t, n, i)?.from != r.from && (e = a);
	for (;;) {
		let r = e.enter(t, n, i);
		if (!r) return e;
		e = r;
	}
}
var ie = class {
	cursor(e = 0) {
		return new de(this, e);
	}
	getChild(e, t = null, n = null) {
		let r = ae(this, e, t, n);
		return r.length ? r[0] : null;
	}
	getChildren(e, t = null, n = null) {
		return ae(this, e, t, n);
	}
	resolve(e, t = 0) {
		return k(this, e, t, !1);
	}
	resolveInner(e, t = 0) {
		return k(this, e, t, !0);
	}
	matchContext(e) {
		return oe(this.parent, e);
	}
	enterUnfinishedNodesBefore(e) {
		let t = this.childBefore(e), n = this;
		for (; t;) {
			let e = t.lastChild;
			if (!e || e.to != t.to) break;
			e.type.isError && e.from == e.to ? (n = t, t = e.prevSibling) : t = e;
		}
		return n;
	}
	get node() {
		return this;
	}
	get next() {
		return this.parent;
	}
}, A = class e extends ie {
	constructor(e, t, n, r) {
		super(), this._tree = e, this.from = t, this.index = n, this._parent = r;
	}
	get type() {
		return this._tree.type;
	}
	get name() {
		return this._tree.type.name;
	}
	get to() {
		return this.from + this._tree.length;
	}
	nextChild(t, n, r, i, a = 0) {
		for (let o = this;;) {
			for (let { children: s, positions: c } = o._tree, l = n > 0 ? s.length : -1; t != l; t += n) {
				let l = s[t], u = c[t] + o.from, d;
				if (a & E.EnterBracketed && l instanceof D && (d = x.get(l)) && !d.overlay && d.bracketed && r >= u && r <= u + l.length || re(i, r, u, u + l.length)) {
					if (l instanceof O) {
						if (a & E.ExcludeBuffers) continue;
						let e = l.findChild(0, l.buffer.length, n, r - u, i);
						if (e > -1) return new j(new se(o, l, t, u), null, e);
					} else if (a & E.IncludeAnonymous || !l.type.isAnonymous || fe(l)) {
						let s;
						if (!(a & E.IgnoreMounts) && (s = x.get(l)) && !s.overlay) return new e(s.tree, u, t, o);
						let c = new e(l, u, t, o);
						return a & E.IncludeAnonymous || !c.type.isAnonymous ? c : c.nextChild(n < 0 ? l.children.length - 1 : 0, n, r, i, a);
					}
				}
			}
			if (a & E.IncludeAnonymous || !o.type.isAnonymous || (t = o.index >= 0 ? o.index + n : n < 0 ? -1 : o._parent._tree.children.length, o = o._parent, !o)) return null;
		}
	}
	get firstChild() {
		return this.nextChild(0, 1, 0, 4);
	}
	get lastChild() {
		return this.nextChild(this._tree.children.length - 1, -1, 0, 4);
	}
	childAfter(e) {
		return this.nextChild(0, 1, e, 2);
	}
	childBefore(e) {
		return this.nextChild(this._tree.children.length - 1, -1, e, -2);
	}
	prop(e) {
		return this._tree.prop(e);
	}
	enter(t, n, r = 0) {
		let i;
		if (!(r & E.IgnoreOverlays) && (i = x.get(this._tree)) && i.overlay) {
			let a = t - this.from, o = r & E.EnterBracketed && i.bracketed;
			for (let { from: t, to: r } of i.overlay) if ((n > 0 || o ? t <= a : t < a) && (n < 0 || o ? r >= a : r > a)) return new e(i.tree, i.overlay[0].from + this.from, -1, this);
		}
		return this.nextChild(0, 1, t, n, r);
	}
	nextSignificantParent() {
		let e = this;
		for (; e.type.isAnonymous && e._parent;) e = e._parent;
		return e;
	}
	get parent() {
		return this._parent ? this._parent.nextSignificantParent() : null;
	}
	get nextSibling() {
		return this._parent && this.index >= 0 ? this._parent.nextChild(this.index + 1, 1, 0, 4) : null;
	}
	get prevSibling() {
		return this._parent && this.index >= 0 ? this._parent.nextChild(this.index - 1, -1, 0, 4) : null;
	}
	get tree() {
		return this._tree;
	}
	toTree() {
		return this._tree;
	}
	toString() {
		return this._tree.toString();
	}
};
function ae(e, t, n, r) {
	let i = e.cursor(), a = [];
	if (!i.firstChild()) return a;
	if (n != null) {
		for (let e = !1; !e;) if (e = i.type.is(n), !i.nextSibling()) return a;
	}
	for (;;) {
		if (r != null && i.type.is(r)) return a;
		if (i.type.is(t) && a.push(i.node), !i.nextSibling()) return r == null ? a : [];
	}
}
function oe(e, t, n = t.length - 1) {
	for (let r = e; n >= 0; r = r.parent) {
		if (!r) return !1;
		if (!r.type.isAnonymous) {
			if (t[n] && t[n] != r.name) return !1;
			n--;
		}
	}
	return !0;
}
var se = class {
	constructor(e, t, n, r) {
		this.parent = e, this.buffer = t, this.index = n, this.start = r;
	}
}, j = class e extends ie {
	get name() {
		return this.type.name;
	}
	get from() {
		return this.context.start + this.context.buffer.buffer[this.index + 1];
	}
	get to() {
		return this.context.start + this.context.buffer.buffer[this.index + 2];
	}
	constructor(e, t, n) {
		super(), this.context = e, this._parent = t, this.index = n, this.type = e.buffer.set.types[e.buffer.buffer[n]];
	}
	child(t, n, r) {
		let { buffer: i } = this.context, a = i.findChild(this.index + 4, i.buffer[this.index + 3], t, n - this.context.start, r);
		return a < 0 ? null : new e(this.context, this, a);
	}
	get firstChild() {
		return this.child(1, 0, 4);
	}
	get lastChild() {
		return this.child(-1, 0, 4);
	}
	childAfter(e) {
		return this.child(1, e, 2);
	}
	childBefore(e) {
		return this.child(-1, e, -2);
	}
	prop(e) {
		return this.type.prop(e);
	}
	enter(t, n, r = 0) {
		if (r & E.ExcludeBuffers) return null;
		let { buffer: i } = this.context, a = i.findChild(this.index + 4, i.buffer[this.index + 3], n > 0 ? 1 : -1, t - this.context.start, n);
		return a < 0 ? null : new e(this.context, this, a);
	}
	get parent() {
		return this._parent || this.context.parent.nextSignificantParent();
	}
	externalSibling(e) {
		return this._parent ? null : this.context.parent.nextChild(this.context.index + e, e, 0, 4);
	}
	get nextSibling() {
		let { buffer: t } = this.context, n = t.buffer[this.index + 3];
		return n < (this._parent ? t.buffer[this._parent.index + 3] : t.buffer.length) ? new e(this.context, this._parent, n) : this.externalSibling(1);
	}
	get prevSibling() {
		let { buffer: t } = this.context, n = this._parent ? this._parent.index + 4 : 0;
		return this.index == n ? this.externalSibling(-1) : new e(this.context, this._parent, t.findChild(n, this.index, -1, 0, 4));
	}
	get tree() {
		return null;
	}
	toTree() {
		let e = [], t = [], { buffer: n } = this.context, r = this.index + 4, i = n.buffer[this.index + 3];
		if (i > r) {
			let a = n.buffer[this.index + 1];
			e.push(n.slice(r, i, a)), t.push(0);
		}
		return new D(this.type, e, t, this.to - this.from);
	}
	toString() {
		return this.context.buffer.childString(this.index);
	}
};
function ce(e) {
	if (!e.length) return null;
	let t = 0, n = e[0];
	for (let r = 1; r < e.length; r++) {
		let i = e[r];
		(i.from > n.from || i.to < n.to) && (n = i, t = r);
	}
	let r = n instanceof A && n.index < 0 ? null : n.parent, i = e.slice();
	return r ? i[t] = r : i.splice(t, 1), new le(i, n);
}
var le = class {
	constructor(e, t) {
		this.heads = e, this.node = t;
	}
	get next() {
		return ce(this.heads);
	}
};
function ue(e, t, n) {
	let r = e.resolveInner(t, n), i = null;
	for (let e = r instanceof A ? r : r.context.parent; e; e = e.parent) if (e.index < 0) {
		let a = e.parent;
		(i ||= [r]).push(a.resolve(t, n)), e = a;
	} else {
		let a = x.get(e.tree);
		if (a && a.overlay && a.overlay[0].from <= t && a.overlay[a.overlay.length - 1].to >= t) {
			let o = new A(a.tree, a.overlay[0].from + e.from, -1, e);
			(i ||= [r]).push(k(o, t, n, !1));
		}
	}
	return i ? ce(i) : r;
}
var de = class {
	get name() {
		return this.type.name;
	}
	constructor(e, t = 0) {
		if (this.buffer = null, this.stack = [], this.index = 0, this.bufferNode = null, this.mode = t & ~E.EnterBracketed, e instanceof A) this.yieldNode(e);
		else {
			this._tree = e.context.parent, this.buffer = e.context;
			for (let t = e._parent; t; t = t._parent) this.stack.unshift(t.index);
			this.bufferNode = e, this.yieldBuf(e.index);
		}
	}
	yieldNode(e) {
		return e ? (this._tree = e, this.type = e.type, this.from = e.from, this.to = e.to, !0) : !1;
	}
	yieldBuf(e, t) {
		this.index = e;
		let { start: n, buffer: r } = this.buffer;
		return this.type = t || r.set.types[r.buffer[e]], this.from = n + r.buffer[e + 1], this.to = n + r.buffer[e + 2], !0;
	}
	yield(e) {
		return e ? e instanceof A ? (this.buffer = null, this.yieldNode(e)) : (this.buffer = e.context, this.yieldBuf(e.index, e.type)) : !1;
	}
	toString() {
		return this.buffer ? this.buffer.buffer.childString(this.index) : this._tree.toString();
	}
	enterChild(e, t, n) {
		if (!this.buffer) return this.yield(this._tree.nextChild(e < 0 ? this._tree._tree.children.length - 1 : 0, e, t, n, this.mode));
		let { buffer: r } = this.buffer, i = r.findChild(this.index + 4, r.buffer[this.index + 3], e, t - this.buffer.start, n);
		return i < 0 ? !1 : (this.stack.push(this.index), this.yieldBuf(i));
	}
	firstChild() {
		return this.enterChild(1, 0, 4);
	}
	lastChild() {
		return this.enterChild(-1, 0, 4);
	}
	childAfter(e) {
		return this.enterChild(1, e, 2);
	}
	childBefore(e) {
		return this.enterChild(-1, e, -2);
	}
	enter(e, t, n = this.mode) {
		return this.buffer ? n & E.ExcludeBuffers ? !1 : this.enterChild(1, e, t) : this.yield(this._tree.enter(e, t, n));
	}
	parent() {
		if (!this.buffer) return this.yieldNode(this.mode & E.IncludeAnonymous ? this._tree._parent : this._tree.parent);
		if (this.stack.length) return this.yieldBuf(this.stack.pop());
		let e = this.mode & E.IncludeAnonymous ? this.buffer.parent : this.buffer.parent.nextSignificantParent();
		return this.buffer = null, this.yieldNode(e);
	}
	sibling(e) {
		if (!this.buffer) return this._tree._parent ? this.yield(this._tree.index < 0 ? null : this._tree._parent.nextChild(this._tree.index + e, e, 0, 4, this.mode)) : !1;
		let { buffer: t } = this.buffer, n = this.stack.length - 1;
		if (e < 0) {
			let e = n < 0 ? 0 : this.stack[n] + 4;
			if (this.index != e) return this.yieldBuf(t.findChild(e, this.index, -1, 0, 4));
		} else {
			let e = t.buffer[this.index + 3];
			if (e < (n < 0 ? t.buffer.length : t.buffer[this.stack[n] + 3])) return this.yieldBuf(e);
		}
		return n < 0 && this.yield(this.buffer.parent.nextChild(this.buffer.index + e, e, 0, 4, this.mode));
	}
	nextSibling() {
		return this.sibling(1);
	}
	prevSibling() {
		return this.sibling(-1);
	}
	atLastNode(e) {
		let t, n, { buffer: r } = this;
		if (r) {
			if (e > 0) {
				if (this.index < r.buffer.buffer.length) return !1;
			} else for (let e = 0; e < this.index; e++) if (r.buffer.buffer[e + 3] < this.index) return !1;
			({index: t, parent: n} = r);
		} else ({index: t, _parent: n} = this._tree);
		for (; n; {index: t, _parent: n} = n) if (t > -1) for (let r = t + e, i = e < 0 ? -1 : n._tree.children.length; r != i; r += e) {
			let e = n._tree.children[r];
			if (this.mode & E.IncludeAnonymous || e instanceof O || !e.type.isAnonymous || fe(e)) return !1;
		}
		return !0;
	}
	move(e, t) {
		if (t && this.enterChild(e, 0, 4)) return !0;
		for (;;) {
			if (this.sibling(e)) return !0;
			if (this.atLastNode(e) || !this.parent()) return !1;
		}
	}
	next(e = !0) {
		return this.move(1, e);
	}
	prev(e = !0) {
		return this.move(-1, e);
	}
	moveTo(e, t = 0) {
		for (; (this.from == this.to || (t < 1 ? this.from >= e : this.from > e) || (t > -1 ? this.to <= e : this.to < e)) && this.parent(););
		for (; this.enterChild(1, e, t););
		return this;
	}
	get node() {
		if (!this.buffer) return this._tree;
		let e = this.bufferNode, t = null, n = 0;
		if (e && e.context == this.buffer) scan: for (let r = this.index, i = this.stack.length; i >= 0;) {
			for (let a = e; a; a = a._parent) if (a.index == r) {
				if (r == this.index) return a;
				t = a, n = i + 1;
				break scan;
			}
			r = this.stack[--i];
		}
		for (let e = n; e < this.stack.length; e++) t = new j(this.buffer, t, this.stack[e]);
		return this.bufferNode = new j(this.buffer, t, this.index);
	}
	get tree() {
		return this.buffer ? null : this._tree._tree;
	}
	iterate(e, t) {
		for (let n = 0;;) {
			let r = !1;
			if (this.type.isAnonymous || e(this) !== !1) {
				if (this.firstChild()) {
					n++;
					continue;
				}
				this.type.isAnonymous || (r = !0);
			}
			for (;;) {
				if (r && t && t(this), r = this.type.isAnonymous, !n) return;
				if (this.nextSibling()) break;
				this.parent(), n--, r = !0;
			}
		}
	}
	matchContext(e) {
		if (!this.buffer) return oe(this.node.parent, e);
		let { buffer: t } = this.buffer, { types: n } = t.set;
		for (let r = e.length - 1, i = this.stack.length - 1; r >= 0; i--) {
			if (i < 0) return oe(this._tree, e, r);
			let a = n[t.buffer[this.stack[i]]];
			if (!a.isAnonymous) {
				if (e[r] && e[r] != a.name) return !1;
				r--;
			}
		}
		return !0;
	}
};
function fe(e) {
	return e.children.some((e) => e instanceof O || !e.type.isAnonymous || fe(e));
}
function pe(e) {
	let { buffer: t, nodeSet: n, maxBufferLength: r = ee, reused: i = [], minRepeatType: a = n.types.length } = e, o = Array.isArray(t) ? new ne(t, t.length) : t, s = n.types, c = 0, l = 0;
	function u(e, t, _, ee, v, y) {
		let { id: b, start: x, end: S, size: C } = o, te = l, w = c;
		if (C < 0) {
			if (o.next(), C == -1) {
				let t = i[b];
				_.push(t), ee.push(x - e);
				return;
			}
			if (C == -3) {
				c = b;
				return;
			}
			if (C == -4) {
				l = b;
				return;
			}
			throw RangeError(`Unrecognized record size: ${C}`);
		}
		let T = s[b], E, D, ne = x - e;
		if (S - x <= r && (D = h(o.pos - t, v))) {
			let t = new Uint16Array(D.size - D.skip), r = o.pos - D.size, i = t.length;
			for (; o.pos > r;) i = g(D.start, t, i);
			E = new O(t, S - D.start, n), ne = D.start - e;
		} else {
			let e = o.pos - C;
			o.next();
			let t = [], s = [], c = b >= a ? b : -1, h = 0, g = S, _ = te;
			for (; o.pos > e;) c >= 0 && o.id == c && o.size >= 0 ? (o.end <= g - r && (p(t, s, x, h, o.end, g, c, _, w), h = t.length, g = o.end, _ = l), o.next()) : y > 2500 ? d(x, e, t, s) : (c >= 0 && o.size == -1 && i[o.id].type == n.types[c] && (h < t.length && p(t, s, x, h, o.end, g, c, _, w), h = t.length + 1, g = o.start, _ = l), u(x, e, t, s, c, y + 1));
			if (c >= 0 && h > 0 && h < t.length && p(t, s, x, h, x, g, c, _, w), t.reverse(), s.reverse(), c > -1 && h > 0) {
				let e = f(T, w);
				E = ge(T, t, s, 0, t.length, 0, S - x, e, e);
			} else E = m(T, t, s, S - x, te - S, w);
		}
		_.push(E), ee.push(ne);
	}
	function d(e, t, i, a) {
		let s = [], c = 0, l = -1;
		for (; o.pos > t;) {
			let { id: e, start: t, end: n, size: i } = o;
			if (i > 4) o.next();
			else if (l > -1 && t < l) break;
			else l < 0 && (l = n - r), s.push(e, t, n), c++, o.next();
		}
		if (c) {
			let t = new Uint16Array(c * 4), r = s[s.length - 2];
			for (let e = s.length - 3, n = 0; e >= 0; e -= 3) t[n++] = s[e], t[n++] = s[e + 1] - r, t[n++] = s[e + 2] - r, t[n++] = n;
			i.push(new O(t, s[2] - r, n)), a.push(r - e);
		}
	}
	function f(e, t) {
		return (n, r, i) => {
			let a = 0, o = n.length - 1, s, c;
			if (o >= 0 && (s = n[o]) instanceof D) {
				if (!o && s.type == e && s.length == i) return s;
				(c = s.prop(b.lookAhead)) && (a = r[o] + s.length + c - i);
			}
			return m(e, n, r, i, a, t);
		};
	}
	function p(e, t, r, i, a, o, s, c, l) {
		let u = [], d = [];
		for (; e.length > i;) u.push(e.pop()), d.push(t.pop() + r - a);
		e.push(m(n.types[s], u, d, o - a, c - o, l)), t.push(a - r);
	}
	function m(e, t, n, r, i, a, o) {
		if (a) {
			let e = [b.contextHash, a];
			o = o ? [e].concat(o) : [e];
		}
		if (i > 25) {
			let e = [b.lookAhead, i];
			o = o ? [e].concat(o) : [e];
		}
		return new D(e, t, n, r, o);
	}
	function h(e, t) {
		let n = o.fork(), i = 0, s = 0, c = 0, l = n.end - r, u = {
			size: 0,
			start: 0,
			skip: 0
		};
		scan: for (let r = n.pos - e; n.pos > r;) {
			let e = n.size;
			if (n.id == t && e >= 0) {
				u.size = i, u.start = s, u.skip = c, c += 4, i += 4, n.next();
				continue;
			}
			let o = n.pos - e;
			if (e < 0 || o < r || n.start < l) break;
			let d = n.id >= a ? 4 : 0, f = n.start;
			for (n.next(); n.pos > o;) {
				if (n.size < 0) {
					if (n.size == -3 || n.size == -4) d += 4;
					else break scan;
				} else n.id >= a && (d += 4);
				n.next();
			}
			s = f, i += e, c += d;
		}
		return (t < 0 || i == e) && (u.size = i, u.start = s, u.skip = c), u.size > 4 ? u : void 0;
	}
	function g(e, t, n) {
		let { id: r, start: i, end: s, size: u } = o;
		if (o.next(), u >= 0 && r < a) {
			let a = n;
			if (u > 4) {
				let r = o.pos - (u - 4);
				for (; o.pos > r;) n = g(e, t, n);
			}
			t[--n] = a, t[--n] = s - e, t[--n] = i - e, t[--n] = r;
		} else u == -3 ? c = r : u == -4 && (l = r);
		return n;
	}
	let _ = [], v = [];
	for (; o.pos > 0;) u(e.start || 0, e.bufferStart || 0, _, v, -1, 0);
	let y = e.length ?? (_.length ? v[0] + _[0].length : 0);
	return new D(s[e.topID], _.reverse(), v.reverse(), y);
}
var me = /* @__PURE__ */ new WeakMap();
function he(e, t) {
	if (!e.isAnonymous || t instanceof O || t.type != e) return 1;
	let n = me.get(t);
	if (n == null) {
		n = 1;
		for (let r of t.children) {
			if (r.type != e || !(r instanceof D)) {
				n = 1;
				break;
			}
			n += he(e, r);
		}
		me.set(t, n);
	}
	return n;
}
function ge(e, t, n, r, i, a, o, s, c) {
	let l = 0;
	for (let n = r; n < i; n++) l += he(e, t[n]);
	let u = Math.ceil(l * 1.5 / 8), d = [], f = [];
	function p(t, n, r, i, o) {
		for (let s = r; s < i;) {
			let r = s, l = n[s], m = he(e, t[s]);
			for (s++; s < i; s++) {
				let n = he(e, t[s]);
				if (m + n >= u) break;
				m += n;
			}
			if (s == r + 1) {
				if (m > u) {
					let e = t[r];
					p(e.children, e.positions, 0, e.children.length, n[r] + o);
					continue;
				}
				d.push(t[r]);
			} else {
				let i = n[s - 1] + t[s - 1].length - l;
				d.push(ge(e, t, n, r, s, l, i, null, c));
			}
			f.push(l + o - a);
		}
	}
	return p(t, n, r, i, 0), (s || c)(d, f, o);
}
var _e = class {
	constructor() {
		this.map = /* @__PURE__ */ new WeakMap();
	}
	setBuffer(e, t, n) {
		let r = this.map.get(e);
		r || this.map.set(e, r = /* @__PURE__ */ new Map()), r.set(t, n);
	}
	getBuffer(e, t) {
		let n = this.map.get(e);
		return n && n.get(t);
	}
	set(e, t) {
		e instanceof j ? this.setBuffer(e.context.buffer, e.index, t) : e instanceof A && this.map.set(e.tree, t);
	}
	get(e) {
		return e instanceof j ? this.getBuffer(e.context.buffer, e.index) : e instanceof A ? this.map.get(e.tree) : void 0;
	}
	cursorSet(e, t) {
		e.buffer ? this.setBuffer(e.buffer.buffer, e.index, t) : this.map.set(e.tree, t);
	}
	cursorGet(e) {
		return e.buffer ? this.getBuffer(e.buffer.buffer, e.index) : this.map.get(e.tree);
	}
}, M = class e {
	constructor(e, t, n, r, i = !1, a = !1) {
		this.from = e, this.to = t, this.tree = n, this.offset = r, this.open = !!i | (a ? 2 : 0);
	}
	get openStart() {
		return (this.open & 1) > 0;
	}
	get openEnd() {
		return (this.open & 2) > 0;
	}
	static addTree(t, n = [], r = !1) {
		let i = [new e(0, t.length, t, 0, !1, r)];
		for (let e of n) e.to > t.length && i.push(e);
		return i;
	}
	static applyChanges(t, n, r = 128) {
		if (!n.length) return t;
		let i = [], a = 1, o = t.length ? t[0] : null;
		for (let s = 0, c = 0, l = 0;; s++) {
			let u = s < n.length ? n[s] : null, d = u ? u.fromA : 1e9;
			if (d - c >= r) for (; o && o.from < d;) {
				let n = o;
				if (c >= n.from || d <= n.to || l) {
					let t = Math.max(n.from, c) - l, r = Math.min(n.to, d) - l;
					n = t >= r ? null : new e(t, r, n.tree, n.offset + l, s > 0, !!u);
				}
				if (n && i.push(n), o.to > d) break;
				o = a < t.length ? t[a++] : null;
			}
			if (!u) break;
			c = u.toA, l = u.toA - u.toB;
		}
		return i;
	}
}, ve = class {
	startParse(e, t, n) {
		return typeof e == "string" && (e = new ye(e)), n = n ? n.length ? n.map((e) => new y(e.from, e.to)) : [new y(0, 0)] : [new y(0, e.length)], this.createParse(e, t || [], n);
	}
	parse(e, t, n) {
		let r = this.startParse(e, t, n);
		for (;;) {
			let e = r.advance();
			if (e) return e;
		}
	}
}, ye = class {
	constructor(e) {
		this.string = e;
	}
	get length() {
		return this.string.length;
	}
	chunk(e) {
		return this.string.slice(e);
	}
	get lineChunks() {
		return !1;
	}
	read(e, t) {
		return this.string.slice(e, t);
	}
};
function be(e) {
	return (t, n, r, i) => new Te(t, e, n, r, i);
}
var xe = class {
	constructor(e, t, n, r, i, a) {
		this.parser = e, this.parse = t, this.overlay = n, this.bracketed = r, this.target = i, this.from = a;
	}
};
function Se(e) {
	if (!e.length || e.some((e) => e.from >= e.to)) throw RangeError("Invalid inner parse ranges given: " + JSON.stringify(e));
}
var Ce = class {
	constructor(e, t, n, r, i, a, o, s) {
		this.parser = e, this.predicate = t, this.mounts = n, this.index = r, this.start = i, this.bracketed = a, this.target = o, this.prev = s, this.depth = 0, this.ranges = [];
	}
}, we = new b({ perNode: !0 }), Te = class {
	constructor(e, t, n, r, i) {
		this.nest = t, this.input = n, this.fragments = r, this.ranges = i, this.inner = [], this.innerDone = 0, this.baseTree = null, this.stoppedAt = null, this.baseParse = e;
	}
	advance() {
		if (this.baseParse) {
			let e = this.baseParse.advance();
			if (!e) return null;
			if (this.baseParse = null, this.baseTree = e, this.startInner(), this.stoppedAt != null) for (let e of this.inner) e.parse.stopAt(this.stoppedAt);
		}
		if (this.innerDone == this.inner.length) {
			let e = this.baseTree;
			return this.stoppedAt != null && (e = new D(e.type, e.children, e.positions, e.length, e.propValues.concat([[we, this.stoppedAt]]))), e;
		}
		let e = this.inner[this.innerDone], t = e.parse.advance();
		if (t) {
			this.innerDone++;
			let n = Object.assign(Object.create(null), e.target.props);
			n[b.mounted.id] = new x(t, e.overlay, e.parser, e.bracketed), e.target.props = n;
		}
		return null;
	}
	get parsedPos() {
		if (this.baseParse) return 0;
		let e = this.input.length;
		for (let t = this.innerDone; t < this.inner.length; t++) this.inner[t].from < e && (e = Math.min(e, this.inner[t].parse.parsedPos));
		return e;
	}
	stopAt(e) {
		if (this.stoppedAt = e, this.baseParse) this.baseParse.stopAt(e);
		else for (let t = this.innerDone; t < this.inner.length; t++) this.inner[t].parse.stopAt(e);
	}
	startInner() {
		let e = new je(this.fragments), t = null, n = null, r = new de(new A(this.baseTree, this.ranges[0].from, 0, null), E.IncludeAnonymous | E.IgnoreMounts);
		scan: for (let i, a;;) {
			let o = !0, s;
			if (this.stoppedAt != null && r.from >= this.stoppedAt) o = !1;
			else if (e.hasNode(r)) {
				if (t) {
					let e = t.mounts.find((e) => e.frag.from <= r.from && e.frag.to >= r.to && e.mount.overlay);
					if (e) for (let n of e.mount.overlay) {
						let i = n.from + e.pos, a = n.to + e.pos;
						i >= r.from && a <= r.to && !t.ranges.some((e) => e.from < a && e.to > i) && t.ranges.push({
							from: i,
							to: a
						});
					}
				}
				o = !1;
			} else if (n && (a = Ee(n.ranges, r.from, r.to))) o = a != 2;
			else if (!r.type.isAnonymous && (i = this.nest(r, this.input)) && (r.from < r.to || !i.overlay)) {
				r.tree || (Oe(r), t && t.depth++, n && n.depth++);
				let a = e.findMounts(r.from, i.parser);
				if (typeof i.overlay == "function") t = new Ce(i.parser, i.overlay, a, this.inner.length, r.from, !!i.bracketed, r.tree, t);
				else {
					let e = Me(this.ranges, i.overlay || (r.from < r.to ? [new y(r.from, r.to)] : []));
					e.length && Se(e), (e.length || !i.overlay) && this.inner.push(new xe(i.parser, e.length ? i.parser.startParse(this.input, Pe(a, e), e) : i.parser.startParse(""), i.overlay ? i.overlay.map((e) => new y(e.from - r.from, e.to - r.from)) : null, !!i.bracketed, r.tree, e.length ? e[0].from : r.from)), i.overlay ? e.length && (n = {
						ranges: e,
						depth: 0,
						prev: n
					}) : o = !1;
				}
			} else if (t && (s = t.predicate(r)) && (s === !0 && (s = new y(r.from, r.to)), s.from < s.to)) {
				let e = t.ranges.length - 1;
				e >= 0 && t.ranges[e].to == s.from ? t.ranges[e] = {
					from: t.ranges[e].from,
					to: s.to
				} : t.ranges.push(s);
			}
			if (o && r.firstChild()) t && t.depth++, n && n.depth++;
			else for (; !r.nextSibling();) {
				if (!r.parent()) break scan;
				if (t && !--t.depth) {
					let e = Me(this.ranges, t.ranges);
					e.length && (Se(e), this.inner.splice(t.index, 0, new xe(t.parser, t.parser.startParse(this.input, Pe(t.mounts, e), e), t.ranges.map((e) => new y(e.from - t.start, e.to - t.start)), t.bracketed, t.target, e[0].from))), t = t.prev;
				}
				n && !--n.depth && (n = n.prev);
			}
		}
	}
};
function Ee(e, t, n) {
	for (let r of e) {
		if (r.from >= n) break;
		if (r.to > t) return r.from <= t && r.to >= n ? 2 : 1;
	}
	return 0;
}
function De(e, t, n, r, i, a) {
	if (t < n) {
		let o = e.buffer[t + 1];
		r.push(e.slice(t, n, o)), i.push(o - a);
	}
}
function Oe(e) {
	let { node: t } = e, n = [], r = t.context.buffer;
	do
		n.push(e.index), e.parent();
	while (!e.tree);
	let i = e.tree, a = i.children.indexOf(r), o = i.children[a], s = o.buffer, c = [a];
	function l(e, r, i, a, u, d) {
		let f = n[d], p = [], m = [];
		De(o, e, f, p, m, a);
		let h = s[f + 1], g = s[f + 2];
		c.push(p.length);
		let _ = d ? l(f + 4, s[f + 3], o.set.types[s[f]], h, g - h, d - 1) : t.toTree();
		return p.push(_), m.push(h - a), De(o, s[f + 3], r, p, m, a), new D(i, p, m, u);
	}
	i.children[a] = l(0, s.length, C.none, 0, o.length, n.length - 1);
	for (let t of c) {
		let n = e.tree.children[t], r = e.tree.positions[t];
		e.yield(new A(n, r + e.from, t, e._tree));
	}
}
var ke = class {
	constructor(e, t) {
		this.offset = t, this.done = !1, this.cursor = e.cursor(E.IncludeAnonymous | E.IgnoreMounts | E.ExcludeBuffers);
	}
	moveTo(e) {
		let { cursor: t } = this, n = e - this.offset;
		for (; !this.done && t.from < n;) if (!(t.to >= n && t.enter(n, 1, E.IncludeAnonymous | E.IgnoreMounts | E.IgnoreOverlays | E.ExcludeBuffers))) {
			if (t.to <= n) t.next(!1) || (this.done = !0);
			else break;
		}
	}
	hasNode(e) {
		if (this.moveTo(e.from), !this.done && this.cursor.from + this.offset == e.from && this.cursor.tree) for (let t = this.cursor.tree;;) {
			if (t == e.tree) return !0;
			if (t.children.length && t.positions[0] == 0 && t.children[0] instanceof D) t = t.children[0];
			else break;
		}
		return !1;
	}
};
function Ae(e) {
	let t = e.tree.prop(we);
	return t == null ? e.to : Math.min(e.to, t - e.offset);
}
var je = class {
	constructor(e) {
		if (this.fragments = e, this.curTo = 0, this.fragI = 0, e.length) {
			let t = this.curFrag = e[0];
			this.curTo = Ae(t), this.inner = new ke(t.tree, -t.offset);
		} else this.curFrag = this.inner = null;
	}
	hasNode(e) {
		for (; this.curFrag && e.from >= this.curTo;) this.nextFrag();
		return this.curFrag && this.curFrag.from <= e.from && this.curTo >= e.to && this.inner.hasNode(e);
	}
	nextFrag() {
		if (this.fragI++, this.fragI == this.fragments.length) this.curFrag = this.inner = null;
		else {
			let e = this.curFrag = this.fragments[this.fragI];
			this.curTo = Ae(e), this.inner = new ke(e.tree, -e.offset);
		}
	}
	findMounts(e, t) {
		let n = [];
		if (this.inner) {
			this.inner.cursor.moveTo(e, 1);
			for (let e = this.inner.cursor.node; e; e = e.parent) {
				let r = e.tree?.prop(b.mounted);
				if (r && r.parser == t) for (let t = this.fragI; t < this.fragments.length; t++) {
					let i = this.fragments[t];
					if (i.from >= e.to) break;
					i.tree == this.curFrag.tree && n.push({
						frag: i,
						pos: e.from - i.offset,
						mount: r
					});
				}
			}
		}
		return n;
	}
};
function Me(e, t) {
	let n = null, r = t;
	for (let i = 1, a = 0; i < e.length; i++) {
		let o = e[i - 1].to, s = e[i].from;
		for (; a < r.length; a++) {
			let e = r[a];
			if (e.from >= s) break;
			e.to <= o || (n || (r = n = t.slice()), e.from < o ? (n[a] = new y(e.from, o), e.to > s && n.splice(a + 1, 0, new y(s, e.to))) : e.to > s ? n[a--] = new y(s, e.to) : n.splice(a--, 1));
		}
	}
	return r;
}
function Ne(e, t, n, r) {
	let i = 0, a = 0, o = !1, s = !1, c = -1e9, l = [];
	for (;;) {
		let u = i == e.length ? 1e9 : o ? e[i].to : e[i].from, d = a == t.length ? 1e9 : s ? t[a].to : t[a].from;
		if (o != s) {
			let e = Math.max(c, n), t = Math.min(u, d, r);
			e < t && l.push(new y(e, t));
		}
		if (c = Math.min(u, d), c == 1e9) break;
		u == c && (o ? (o = !1, i++) : o = !0), d == c && (s ? (s = !1, a++) : s = !0);
	}
	return l;
}
function Pe(e, t) {
	let n = [];
	for (let { pos: r, mount: i, frag: a } of e) {
		let e = r + (i.overlay ? i.overlay[0].from : 0), o = e + i.tree.length, s = Math.max(a.from, e), c = Math.min(a.to, o);
		if (i.overlay) {
			let o = Ne(t, i.overlay.map((e) => new y(e.from + r, e.to + r)), s, c);
			for (let t = 0, r = s;; t++) {
				let s = t == o.length, l = s ? c : o[t].from;
				if (l > r && n.push(new M(r, l, i.tree, -e, a.from >= r || a.openStart, a.to <= l || a.openEnd)), s) break;
				r = o[t].to;
			}
		} else n.push(new M(s, c, i.tree, -e, a.from >= e || a.openStart, a.to <= o || a.openEnd));
	}
	return n;
}
// node_modules/.pnpm/@lezer+highlight@1.2.5/node_modules/@lezer/highlight/dist/index.js
var Fe = 0, N = class e {
	constructor(e, t, n, r) {
		this.name = e, this.set = t, this.base = n, this.modified = r, this.id = Fe++;
	}
	toString() {
		let { name: e } = this;
		for (let t of this.modified) t.name && (e = `${t.name}(${e})`);
		return e;
	}
	static define(t, n) {
		let r = typeof t == "string" ? t : "?";
		if (t instanceof e && (n = t), n?.base) throw Error("Can not derive from a modified tag");
		let i = new e(r, [], null, []);
		if (i.set.push(i), n) for (let e of n.set) i.set.push(e);
		return i;
	}
	static defineModifier(e) {
		let t = new Le(e);
		return (e) => e.modified.indexOf(t) > -1 ? e : Le.get(e.base || e, e.modified.concat(t).sort((e, t) => e.id - t.id));
	}
}, Ie = 0, Le = class e {
	constructor(e) {
		this.name = e, this.instances = [], this.id = Ie++;
	}
	static get(t, n) {
		if (!n.length) return t;
		let r = n[0].instances.find((e) => e.base == t && Re(n, e.modified));
		if (r) return r;
		let i = [], a = new N(t.name, i, t, n);
		for (let e of n) e.instances.push(a);
		let o = ze(n);
		for (let n of t.set) if (!n.modified.length) for (let t of o) i.push(e.get(n, t));
		return a;
	}
};
function Re(e, t) {
	return e.length == t.length && e.every((e, n) => e == t[n]);
}
function ze(e) {
	let t = [[]];
	for (let n = 0; n < e.length; n++) for (let r = 0, i = t.length; r < i; r++) t.push(t[r].concat(e[n]));
	return t.sort((e, t) => t.length - e.length);
}
function Be(e) {
	let t = Object.create(null);
	for (let n in e) {
		let r = e[n];
		Array.isArray(r) || (r = [r]);
		for (let e of n.split(" ")) if (e) {
			let n = [], i = 2, a = e;
			for (let t = 0;;) {
				if (a == "..." && t > 0 && t + 3 == e.length) {
					i = 1;
					break;
				}
				let r = /^"(?:[^"\\]|\\.)*?"|[^\/!]+/.exec(a);
				if (!r) throw RangeError("Invalid path: " + e);
				if (n.push(r[0] == "*" ? "" : r[0][0] == "\"" ? JSON.parse(r[0]) : r[0]), t += r[0].length, t == e.length) break;
				let o = e[t++];
				if (t == e.length && o == "!") {
					i = 0;
					break;
				}
				if (o != "/") throw RangeError("Invalid path: " + e);
				a = e.slice(t);
			}
			let o = n.length - 1, s = n[o];
			if (!s) throw RangeError("Invalid path: " + e);
			t[s] = new P(r, i, o > 0 ? n.slice(0, o) : null).sort(t[s]);
		}
	}
	return Ve.add(t);
}
var Ve = new b({ combine(e, t) {
	let n, r, i;
	for (; e || t;) {
		if (!e || t && e.depth <= t.depth ? (i = t, t = t.next) : (i = e, e = e.next), n && n.mode == i.mode && !i.context && !n.context) continue;
		let a = new P(i.tags, i.mode, i.context);
		n ? n.next = a : r = a, n = a;
	}
	return r;
} }), P = class {
	constructor(e, t, n, r) {
		this.tags = e, this.mode = t, this.context = n, this.next = r;
	}
	get opaque() {
		return this.mode == 0;
	}
	get inherit() {
		return this.mode == 1;
	}
	sort(e) {
		return !e || e.depth < this.depth ? (this.next = e, this) : (e.next = this.sort(e.next), e);
	}
	get depth() {
		return this.context ? this.context.length : 0;
	}
};
P.empty = new P([], 2, null);
function He(e, t) {
	let n = Object.create(null);
	for (let t of e) if (!Array.isArray(t.tag)) n[t.tag.id] = t.class;
	else for (let e of t.tag) n[e.id] = t.class;
	let { scope: r, all: i = null } = t || {};
	return {
		style: (e) => {
			let t = i;
			for (let r of e) for (let e of r.set) {
				let r = n[e.id];
				if (r) {
					t = t ? t + " " + r : r;
					break;
				}
			}
			return t;
		},
		scope: r
	};
}
function Ue(e, t) {
	let n = null;
	for (let r of e) {
		let e = r.style(t);
		e && (n = n ? n + " " + e : e);
	}
	return n;
}
function We(e, t, n, r = 0, i = e.length) {
	let a = new Ge(r, Array.isArray(t) ? t : [t], n);
	a.highlightRange(e.cursor(), r, i, "", a.highlighters), a.flush(i);
}
var Ge = class {
	constructor(e, t, n) {
		this.at = e, this.highlighters = t, this.span = n, this.class = "";
	}
	startSpan(e, t) {
		t != this.class && (this.flush(e), e > this.at && (this.at = e), this.class = t);
	}
	flush(e) {
		e > this.at && this.class && this.span(this.at, e, this.class);
	}
	highlightRange(e, t, n, r, i) {
		let { type: a, from: o, to: s } = e;
		if (o >= n || s <= t) return;
		a.isTop && (i = this.highlighters.filter((e) => !e.scope || e.scope(a)));
		let c = r, l = Ke(e) || P.empty, u = Ue(i, l.tags);
		if (u && (c && (c += " "), c += u, l.mode == 1 && (r += (r ? " " : "") + u)), this.startSpan(Math.max(t, o), c), l.opaque) return;
		let d = e.tree && e.tree.prop(b.mounted);
		if (d && d.overlay) {
			let a = e.node.enter(d.overlay[0].from + o, 1), l = this.highlighters.filter((e) => !e.scope || e.scope(d.tree.type)), u = e.firstChild();
			for (let f = 0, p = o;; f++) {
				let m = f < d.overlay.length ? d.overlay[f] : null, h = m ? m.from + o : s, g = Math.max(t, p), _ = Math.min(n, h);
				if (g < _ && u) for (; e.from < _ && (this.highlightRange(e, g, _, r, i), this.startSpan(Math.min(_, e.to), c), !(e.to >= h || !e.nextSibling())););
				if (!m || h > n) break;
				p = m.to + o, p > t && (this.highlightRange(a.cursor(), Math.max(t, m.from + o), Math.min(n, p), "", l), this.startSpan(Math.min(n, p), c));
			}
			u && e.parent();
		} else if (e.firstChild()) {
			d && (r = "");
			do
				if (!(e.to <= t)) {
					if (e.from >= n) break;
					this.highlightRange(e, t, n, r, i), this.startSpan(Math.min(n, e.to), c);
				}
			while (e.nextSibling());
			e.parent();
		}
	}
};
function Ke(e) {
	let t = e.type.prop(Ve);
	for (; t && t.context && !e.matchContext(t.context);) t = t.next;
	return t || null;
}
var F = N.define, qe = F(), I = F(), Je = F(I), Ye = F(I), L = F(), Xe = F(L), Ze = F(L), R = F(), z = F(R), B = F(), V = F(), Qe = F(), H = F(Qe), $e = F(), U = {
	comment: qe,
	lineComment: F(qe),
	blockComment: F(qe),
	docComment: F(qe),
	name: I,
	variableName: F(I),
	typeName: Je,
	tagName: F(Je),
	propertyName: Ye,
	attributeName: F(Ye),
	className: F(I),
	labelName: F(I),
	namespace: F(I),
	macroName: F(I),
	literal: L,
	string: Xe,
	docString: F(Xe),
	character: F(Xe),
	attributeValue: F(Xe),
	number: Ze,
	integer: F(Ze),
	float: F(Ze),
	bool: F(L),
	regexp: F(L),
	escape: F(L),
	color: F(L),
	url: F(L),
	keyword: B,
	self: F(B),
	null: F(B),
	atom: F(B),
	unit: F(B),
	modifier: F(B),
	operatorKeyword: F(B),
	controlKeyword: F(B),
	definitionKeyword: F(B),
	moduleKeyword: F(B),
	operator: V,
	derefOperator: F(V),
	arithmeticOperator: F(V),
	logicOperator: F(V),
	bitwiseOperator: F(V),
	compareOperator: F(V),
	updateOperator: F(V),
	definitionOperator: F(V),
	typeOperator: F(V),
	controlOperator: F(V),
	punctuation: Qe,
	separator: F(Qe),
	bracket: H,
	angleBracket: F(H),
	squareBracket: F(H),
	paren: F(H),
	brace: F(H),
	content: R,
	heading: z,
	heading1: F(z),
	heading2: F(z),
	heading3: F(z),
	heading4: F(z),
	heading5: F(z),
	heading6: F(z),
	contentSeparator: F(R),
	list: F(R),
	quote: F(R),
	emphasis: F(R),
	strong: F(R),
	link: F(R),
	monospace: F(R),
	strikethrough: F(R),
	inserted: F(),
	deleted: F(),
	changed: F(),
	invalid: F(),
	meta: $e,
	documentMeta: F($e),
	annotation: F($e),
	processingInstruction: F($e),
	definition: N.defineModifier("definition"),
	constant: N.defineModifier("constant"),
	function: N.defineModifier("function"),
	standard: N.defineModifier("standard"),
	local: N.defineModifier("local"),
	special: N.defineModifier("special")
};
for (let e in U) {
	let t = U[e];
	t instanceof N && (t.name = e);
}
He([
	{
		tag: U.link,
		class: "tok-link"
	},
	{
		tag: U.heading,
		class: "tok-heading"
	},
	{
		tag: U.emphasis,
		class: "tok-emphasis"
	},
	{
		tag: U.strong,
		class: "tok-strong"
	},
	{
		tag: U.keyword,
		class: "tok-keyword"
	},
	{
		tag: U.atom,
		class: "tok-atom"
	},
	{
		tag: U.bool,
		class: "tok-bool"
	},
	{
		tag: U.url,
		class: "tok-url"
	},
	{
		tag: U.labelName,
		class: "tok-labelName"
	},
	{
		tag: U.inserted,
		class: "tok-inserted"
	},
	{
		tag: U.deleted,
		class: "tok-deleted"
	},
	{
		tag: U.literal,
		class: "tok-literal"
	},
	{
		tag: U.string,
		class: "tok-string"
	},
	{
		tag: U.number,
		class: "tok-number"
	},
	{
		tag: [
			U.regexp,
			U.escape,
			U.special(U.string)
		],
		class: "tok-string2"
	},
	{
		tag: U.variableName,
		class: "tok-variableName"
	},
	{
		tag: U.local(U.variableName),
		class: "tok-variableName tok-local"
	},
	{
		tag: U.definition(U.variableName),
		class: "tok-variableName tok-definition"
	},
	{
		tag: U.special(U.variableName),
		class: "tok-variableName2"
	},
	{
		tag: U.definition(U.propertyName),
		class: "tok-propertyName tok-definition"
	},
	{
		tag: U.typeName,
		class: "tok-typeName"
	},
	{
		tag: U.namespace,
		class: "tok-namespace"
	},
	{
		tag: U.className,
		class: "tok-className"
	},
	{
		tag: U.macroName,
		class: "tok-macroName"
	},
	{
		tag: U.propertyName,
		class: "tok-propertyName"
	},
	{
		tag: U.operator,
		class: "tok-operator"
	},
	{
		tag: U.comment,
		class: "tok-comment"
	},
	{
		tag: U.meta,
		class: "tok-meta"
	},
	{
		tag: U.invalid,
		class: "tok-invalid"
	},
	{
		tag: U.punctuation,
		class: "tok-punctuation"
	}
]);
// node_modules/.pnpm/@codemirror+streamparser@6.0.0/node_modules/@codemirror/streamparser/dist/index.js
function et(e, t, n, r = 0, i = 0) {
	t ?? (t = e.search(/[^\s\u00a0]/), t == -1 && (t = e.length));
	let a = i;
	for (let i = r; i < t; i++) e.charCodeAt(i) == 9 ? a += n - a % n : a++;
	return a;
}
var tt = class {
	constructor(e, t, n, r) {
		this.string = e, this.tabSize = t, this.indentUnit = n, this.overrideIndent = r, this.pos = 0, this.start = 0, this.lastColumnPos = 0, this.lastColumnValue = 0;
	}
	eol() {
		return this.pos >= this.string.length;
	}
	sol() {
		return this.pos == 0;
	}
	peek() {
		return this.string.charAt(this.pos) || void 0;
	}
	next() {
		if (this.pos < this.string.length) return this.string.charAt(this.pos++);
	}
	eat(e) {
		let t = this.string.charAt(this.pos), n;
		if (n = typeof e == "string" ? t == e : t && (e instanceof RegExp ? e.test(t) : e(t)), n) return ++this.pos, t;
	}
	eatWhile(e) {
		let t = this.pos;
		for (; this.eat(e););
		return this.pos > t;
	}
	eatSpace() {
		let e = this.pos;
		for (; /[\s\u00a0]/.test(this.string.charAt(this.pos));) ++this.pos;
		return this.pos > e;
	}
	skipToEnd() {
		this.pos = this.string.length;
	}
	skipTo(e) {
		let t = this.string.indexOf(e, this.pos);
		if (t > -1) return this.pos = t, !0;
	}
	backUp(e) {
		this.pos -= e;
	}
	column() {
		return this.lastColumnPos < this.start && (this.lastColumnValue = et(this.string, this.start, this.tabSize, this.lastColumnPos, this.lastColumnValue), this.lastColumnPos = this.start), this.lastColumnValue;
	}
	indentation() {
		return this.overrideIndent ?? et(this.string, null, this.tabSize);
	}
	match(e, t, n) {
		if (typeof e == "string") {
			let r = (e) => n ? e.toLowerCase() : e;
			return r(this.string.substr(this.pos, e.length)) == r(e) ? (t !== !1 && (this.pos += e.length), !0) : null;
		}
		{
			let n = this.string.slice(this.pos).match(e);
			return n && n.index > 0 ? null : (n && t !== !1 && (this.pos += n[0].length), n);
		}
	}
	current() {
		return this.string.slice(this.start, this.pos);
	}
}, W = /*@__PURE__*/ new b();
function nt(e) {
	return i.define({ combine: e ? (t) => t.concat(e) : void 0 });
}
var rt = /*@__PURE__*/ new b(), G = class {
	constructor(e, t, n = [], i = "") {
		this.data = e, this.name = i, r.prototype.hasOwnProperty("tree") || Object.defineProperty(r.prototype, "tree", { get() {
			return K(this);
		} }), this.parser = t, this.extension = [J.of(this), r.languageData.of((e, t, n) => {
			let r = it(e, t, n), i = r.type.prop(W);
			if (!i) return [];
			let a = e.facet(i), o = r.type.prop(rt);
			if (o) {
				let i = r.resolve(t - r.from, n);
				for (let t of o) if (t.test(i, e)) {
					let n = e.facet(t.facet);
					return t.type == "replace" ? n : n.concat(a);
				}
			}
			return a;
		})].concat(n);
	}
	isActiveAt(e, t, n = -1) {
		return it(e, t, n).type.prop(W) == this.data;
	}
	findRegions(e) {
		let t = e.facet(J);
		if (t?.data == this.data) return [{
			from: 0,
			to: e.doc.length
		}];
		if (!t || !t.allowsNesting) return [];
		let n = [], r = (e, t) => {
			if (e.prop(W) == this.data) {
				n.push({
					from: t,
					to: t + e.length
				});
				return;
			}
			let i = e.prop(b.mounted);
			if (i) {
				if (i.tree.prop(W) == this.data) {
					if (i.overlay) for (let e of i.overlay) n.push({
						from: e.from + t,
						to: e.to + t
					});
					else n.push({
						from: t,
						to: t + e.length
					});
					return;
				}
				if (i.overlay) {
					let e = n.length;
					if (r(i.tree, i.overlay[0].from + t), n.length > e) return;
				}
			}
			for (let n = 0; n < e.children.length; n++) {
				let i = e.children[n];
				i instanceof D && r(i, e.positions[n] + t);
			}
		};
		return r(K(e), 0), n;
	}
	get allowsNesting() {
		return !0;
	}
};
G.setState = /*@__PURE__*/ a.define();
function it(e, t, n) {
	let r = e.facet(J), i = K(e).topNode;
	if (!r || r.allowsNesting) for (let e = i; e; e = e.enter(t, n, E.ExcludeBuffers | E.EnterBracketed)) e.type.isTop && (i = e);
	return i;
}
var at = class e extends G {
	constructor(e, t, n) {
		super(e, t, [], n), this.parser = t;
	}
	static define(t) {
		let n = nt(t.languageData);
		return new e(n, t.parser.configure({ props: [W.add((e) => e.isTop ? n : void 0)] }), t.name);
	}
	configure(t, n) {
		return new e(this.data, this.parser.configure(t), n || this.name);
	}
	get allowsNesting() {
		return this.parser.hasWrappers();
	}
};
function K(e) {
	let t = e.field(G.state, !1);
	return t ? t.tree : D.empty;
}
var ot = class {
	constructor(e) {
		this.doc = e, this.cursorPos = 0, this.string = "", this.cursor = e.iter();
	}
	get length() {
		return this.doc.length;
	}
	syncTo(e) {
		return this.string = this.cursor.next(e - this.cursorPos).value, this.cursorPos = e + this.string.length, this.cursorPos - this.string.length;
	}
	chunk(e) {
		return this.syncTo(e), this.string;
	}
	get lineChunks() {
		return !0;
	}
	read(e, t) {
		let n = this.cursorPos - this.string.length;
		return e < n || t >= this.cursorPos ? this.doc.sliceString(e, t) : this.string.slice(e - n, t - n);
	}
}, q = null, st = class e {
	constructor(e, t, n = [], r, i, a, o, s) {
		this.parser = e, this.state = t, this.fragments = n, this.tree = r, this.treeLen = i, this.viewport = a, this.skipped = o, this.scheduleOn = s, this.parse = null, this.tempSkipped = [];
	}
	static create(t, n, r) {
		return new e(t, n, [], D.empty, 0, r, [], null);
	}
	startParse() {
		return this.parser.startParse(new ot(this.state.doc), this.fragments);
	}
	work(e, t) {
		return t != null && t >= this.state.doc.length && (t = void 0), this.tree != D.empty && this.isDone(t ?? this.state.doc.length) ? (this.takeTree(), !0) : this.withContext(() => {
			if (typeof e == "number") {
				let t = Date.now() + e;
				e = () => Date.now() > t;
			}
			for (this.parse ||= this.startParse(), t != null && (this.parse.stoppedAt == null || this.parse.stoppedAt > t) && t < this.state.doc.length && this.parse.stopAt(t);;) {
				let n = this.parse.advance();
				if (n) {
					if (this.fragments = this.withoutTempSkipped(M.addTree(n, this.fragments, this.parse.stoppedAt != null)), this.treeLen = this.parse.stoppedAt ?? this.state.doc.length, this.tree = n, this.parse = null, this.treeLen < (t ?? this.state.doc.length)) this.parse = this.startParse();
					else return !0;
				}
				if (e()) return !1;
			}
		});
	}
	takeTree() {
		let e, t;
		this.parse && (e = this.parse.parsedPos) >= this.treeLen && ((this.parse.stoppedAt == null || this.parse.stoppedAt > e) && this.parse.stopAt(e), this.withContext(() => {
			for (; !(t = this.parse.advance()););
		}), this.treeLen = e, this.tree = t, this.fragments = this.withoutTempSkipped(M.addTree(this.tree, this.fragments, !0)), this.parse = null);
	}
	withContext(e) {
		let t = q;
		q = this;
		try {
			return e();
		} finally {
			q = t;
		}
	}
	withoutTempSkipped(e) {
		for (let t; t = this.tempSkipped.pop();) e = ct(e, t.from, t.to);
		return e;
	}
	changes(t, n) {
		let { fragments: r, tree: i, treeLen: a, viewport: o, skipped: s } = this;
		if (this.takeTree(), !t.empty) {
			let e = [];
			if (t.iterChangedRanges((t, n, r, i) => e.push({
				fromA: t,
				toA: n,
				fromB: r,
				toB: i
			})), r = M.applyChanges(r, e), i = D.empty, a = 0, o = {
				from: t.mapPos(o.from, -1),
				to: t.mapPos(o.to, 1)
			}, this.skipped.length) {
				s = [];
				for (let e of this.skipped) {
					let n = t.mapPos(e.from, 1), r = t.mapPos(e.to, -1);
					n < r && s.push({
						from: n,
						to: r
					});
				}
			}
		}
		return new e(this.parser, n, r, i, a, o, s, this.scheduleOn);
	}
	updateViewport(e) {
		if (this.viewport.from == e.from && this.viewport.to == e.to) return !1;
		this.viewport = e;
		let t = this.skipped.length;
		for (let t = 0; t < this.skipped.length; t++) {
			let { from: n, to: r } = this.skipped[t];
			n < e.to && r > e.from && (this.fragments = ct(this.fragments, n, r), this.skipped.splice(t--, 1));
		}
		return this.skipped.length >= t ? !1 : (this.reset(), !0);
	}
	reset() {
		this.parse &&= (this.takeTree(), null);
	}
	skipUntilInView(e, t) {
		this.skipped.push({
			from: e,
			to: t
		});
	}
	static getSkippingParser(e) {
		return new class extends ve {
			createParse(t, n, r) {
				let i = r[0].from, a = r[r.length - 1].to;
				return {
					parsedPos: i,
					advance() {
						let t = q;
						if (t) {
							for (let e of r) t.tempSkipped.push(e);
							e && (t.scheduleOn = t.scheduleOn ? Promise.all([t.scheduleOn, e]) : e);
						}
						return this.parsedPos = a, new D(C.none, [], [], a - i);
					},
					stoppedAt: null,
					stopAt() {}
				};
			}
		}();
	}
	isDone(e) {
		e = Math.min(e, this.state.doc.length);
		let t = this.fragments;
		return this.treeLen >= e && t.length && t[0].from == 0 && t[0].to >= e;
	}
	static get() {
		return q;
	}
};
function ct(e, t, n) {
	return M.applyChanges(e, [{
		fromA: t,
		toA: n,
		fromB: t,
		toB: n
	}]);
}
var lt = class e {
	constructor(e) {
		this.context = e, this.tree = e.tree;
	}
	apply(t) {
		if (!t.docChanged && this.tree == this.context.tree) return this;
		let n = this.context.changes(t.changes, t.state), r = this.context.treeLen == t.startState.doc.length ? void 0 : Math.max(t.changes.mapPos(this.context.treeLen), n.viewport.to);
		return n.work(20, r) || n.takeTree(), new e(n);
	}
	static init(t) {
		let n = Math.min(3e3, t.doc.length), r = st.create(t.facet(J).parser, t, {
			from: 0,
			to: n
		});
		return r.work(20, n) || r.takeTree(), new e(r);
	}
};
G.state = /*@__PURE__*/ s.define({
	create: lt.init,
	update(e, t) {
		for (let e of t.effects) if (e.is(G.setState)) return e.value;
		return t.startState.facet(J) == t.state.facet(J) ? e.apply(t) : lt.init(t.state);
	}
});
var ut = (e) => {
	let t = setTimeout(() => e(), 500);
	return () => clearTimeout(t);
};
typeof requestIdleCallback < "u" && (ut = (e) => {
	let t = -1, n = setTimeout(() => {
		t = requestIdleCallback(e, { timeout: 400 });
	}, 100);
	return () => t < 0 ? clearTimeout(n) : cancelIdleCallback(t);
});
var dt = typeof navigator < "u" && navigator.scheduling?.isInputPending ? () => navigator.scheduling.isInputPending() : null, ft = /*@__PURE__*/ g.fromClass(class {
	constructor(e) {
		this.view = e, this.working = null, this.workScheduled = 0, this.chunkEnd = -1, this.chunkBudget = -1, this.work = this.work.bind(this), this.scheduleWork();
	}
	update(e) {
		let t = this.view.state.field(G.state).context;
		(t.updateViewport(e.view.viewport) || this.view.viewport.to > t.treeLen) && this.scheduleWork(), (e.docChanged || e.selectionSet) && (this.view.hasFocus && (this.chunkBudget += 50), this.scheduleWork()), this.checkAsyncSchedule(t);
	}
	scheduleWork() {
		if (this.working) return;
		let { state: e } = this.view, t = e.field(G.state);
		t.tree == t.context.tree && t.context.isDone(e.doc.length) || (this.working = ut(this.work));
	}
	work(e) {
		this.working = null;
		let t = Date.now();
		if (this.chunkEnd < t && (this.chunkEnd < 0 || this.view.hasFocus) && (this.chunkEnd = t + 3e4, this.chunkBudget = 3e3), this.chunkBudget <= 0) return;
		let { state: n, viewport: { to: r } } = this.view, i = n.field(G.state);
		if (i.tree == i.context.tree && i.context.isDone(r + 1e5)) return;
		let a = Date.now() + Math.min(this.chunkBudget, 100, e && !dt ? Math.max(25, e.timeRemaining() - 5) : 1e9), o = i.context.treeLen < r && n.doc.length > r + 1e3, s = i.context.work(() => dt && dt() || Date.now() > a, r + (o ? 0 : 1e5));
		this.chunkBudget -= Date.now() - t, (s || this.chunkBudget <= 0) && (i.context.takeTree(), this.view.dispatch({ effects: G.setState.of(new lt(i.context)) })), this.chunkBudget > 0 && (!s || o) && this.scheduleWork(), this.checkAsyncSchedule(i.context);
	}
	checkAsyncSchedule(e) {
		e.scheduleOn &&= (this.workScheduled++, e.scheduleOn.then(() => this.scheduleWork()).catch((e) => n(this.view.state, e)).then(() => this.workScheduled--), null);
	}
	destroy() {
		this.working && this.working();
	}
	isWorking() {
		return !!(this.working || this.workScheduled > 0);
	}
}, { eventHandlers: { focus() {
	this.scheduleWork();
} } }), J = /*@__PURE__*/ i.define({
	combine(e) {
		return e.length ? e[0] : null;
	},
	enables: (e) => [
		G.state,
		ft,
		u.contentAttributes.compute([e], (t) => {
			let n = t.facet(e);
			return n && n.name ? { "data-language": n.name } : {};
		})
	]
}), pt = class {
	constructor(e, t = []) {
		this.language = e, this.support = t, this.extension = [e, t];
	}
}, mt = class e {
	constructor(e, t, n, r, i, a = void 0) {
		this.name = e, this.alias = t, this.extensions = n, this.filename = r, this.loadFunc = i, this.support = a, this.loading = null;
	}
	load() {
		return this.loading ||= this.loadFunc().then((e) => this.support = e, (e) => {
			throw this.loading = null, e;
		});
	}
	static of(t) {
		let { load: n, support: r } = t;
		if (!n) {
			if (!r) throw RangeError("Must pass either 'load' or 'support' to LanguageDescription.of");
			n = () => Promise.resolve(r);
		}
		return new e(t.name, (t.alias || []).concat(t.name).map((e) => e.toLowerCase()), t.extensions || [], t.filename, n, r);
	}
	static matchFilename(e, t) {
		for (let n of e) if (n.filename && n.filename.test(t)) return n;
		let n = /\.([^.]+)$/.exec(t);
		if (n) {
			for (let t of e) if (t.extensions.indexOf(n[1]) > -1) return t;
		}
		return null;
	}
	static matchLanguageName(e, t, n = !0) {
		t = t.toLowerCase();
		for (let n of e) if (n.alias.some((e) => e == t)) return n;
		if (n) for (let n of e) for (let e of n.alias) {
			let r = t.indexOf(e);
			if (r > -1 && (e.length > 2 || !/\w/.test(t[r - 1]) && !/\w/.test(t[r + e.length]))) return n;
		}
		return null;
	}
}, ht = /*@__PURE__*/ i.define(), gt = /*@__PURE__*/ i.define({ combine: (e) => {
	if (!e.length) return "  ";
	let t = e[0];
	if (!t || /\S/.test(t) || Array.from(t).some((e) => e != t[0])) throw Error("Invalid indent unit: " + JSON.stringify(e[0]));
	return t;
} });
function Y(e) {
	let t = e.facet(gt);
	return t.charCodeAt(0) == 9 ? e.tabSize * t.length : t.length;
}
function _t(e, t) {
	let n = "", r = e.tabSize, i = e.facet(gt)[0];
	if (i == "	") {
		for (; t >= r;) n += "	", t -= r;
		i = " ";
	}
	for (let e = 0; e < t; e++) n += i;
	return n;
}
function vt(e, t) {
	e instanceof r && (e = new yt(e));
	for (let n of e.state.facet(ht)) {
		let r = n(e, t);
		if (r !== void 0) return r;
	}
	let n = K(e.state);
	return n.length >= t ? xt(e, n, t) : null;
}
var yt = class {
	constructor(e, t = {}) {
		this.state = e, this.options = t, this.unit = Y(e);
	}
	lineAt(e, t = 1) {
		let n = this.state.doc.lineAt(e), { simulateBreak: r, simulateDoubleBreak: i } = this.options;
		return r != null && r >= n.from && r <= n.to ? i && r == e ? {
			text: "",
			from: e
		} : (t < 0 ? r < e : r <= e) ? {
			text: n.text.slice(r - n.from),
			from: r
		} : {
			text: n.text.slice(0, r - n.from),
			from: n.from
		} : n;
	}
	textAfterPos(e, t = 1) {
		if (this.options.simulateDoubleBreak && e == this.options.simulateBreak) return "";
		let { text: n, from: r } = this.lineAt(e, t);
		return n.slice(e - r, Math.min(n.length, e + 100 - r));
	}
	column(e, t = 1) {
		let { text: n, from: r } = this.lineAt(e, t), i = this.countColumn(n, e - r), a = this.options.overrideIndentation ? this.options.overrideIndentation(r) : -1;
		return a > -1 && (i += a - this.countColumn(n, n.search(/\S|$/))), i;
	}
	countColumn(e, t = e.length) {
		return c(e, this.state.tabSize, t);
	}
	lineIndent(e, t = 1) {
		let { text: n, from: r } = this.lineAt(e, t), i = this.options.overrideIndentation;
		if (i) {
			let e = i(r);
			if (e > -1) return e;
		}
		return this.countColumn(n, n.search(/\S|$/));
	}
	get simulatedBreak() {
		return this.options.simulateBreak || null;
	}
}, bt = /*@__PURE__*/ new b();
function xt(e, t, n) {
	let r = t.resolveStack(n), i = t.resolveInner(n, -1).resolve(n, 0).enterUnfinishedNodesBefore(n);
	if (i != r.node) {
		let e = [];
		for (let t = i; t && !(t.from < r.node.from || t.to > r.node.to || t.from == r.node.from && t.type == r.node.type); t = t.parent) e.push(t);
		for (let t = e.length - 1; t >= 0; t--) r = {
			node: e[t],
			next: r
		};
	}
	return St(r, e, n);
}
function St(e, t, n) {
	for (let r = e; r; r = r.next) {
		let e = wt(r.node);
		if (e) return e(Et.create(t, n, r));
	}
	return 0;
}
function Ct(e) {
	return e.pos == e.options.simulateBreak && e.options.simulateDoubleBreak;
}
function wt(e) {
	let t = e.type.prop(bt);
	if (t) return t;
	let n = e.firstChild, r;
	if (n && (r = n.type.prop(b.closedBy))) {
		let t = e.lastChild, n = t && r.indexOf(t.name) > -1;
		return (e) => At(e, !0, 1, void 0, n && !Ct(e) ? t.from : void 0);
	}
	return e.parent == null ? Tt : null;
}
function Tt() {
	return 0;
}
var Et = class e extends yt {
	constructor(e, t, n) {
		super(e.state, e.options), this.base = e, this.pos = t, this.context = n;
	}
	get node() {
		return this.context.node;
	}
	static create(t, n, r) {
		return new e(t, n, r);
	}
	get textAfter() {
		return this.textAfterPos(this.pos);
	}
	get baseIndent() {
		return this.baseIndentFor(this.node);
	}
	baseIndentFor(e) {
		let t = this.state.doc.lineAt(e.from);
		for (;;) {
			let n = e.resolve(t.from);
			for (; n.parent && n.parent.from == n.from;) n = n.parent;
			if (Dt(n, e)) break;
			t = this.state.doc.lineAt(n.from);
		}
		return this.lineIndent(t.from);
	}
	continue() {
		return St(this.context.next, this.base, this.pos);
	}
};
function Dt(e, t) {
	for (let n = t; n; n = n.parent) if (e == n) return !0;
	return !1;
}
function Ot(e) {
	let t = e.node, n = t.childAfter(t.from), r = t.lastChild;
	if (!n) return null;
	let i = e.options.simulateBreak, a = e.state.doc.lineAt(n.from), o = i == null || i <= a.from ? a.to : Math.min(a.to, i);
	for (let e = n.to;;) {
		let i = t.childAfter(e);
		if (!i || i == r) return null;
		if (!i.type.isSkipped) {
			if (i.from >= o) return null;
			let e = /^ */.exec(a.text.slice(n.to - a.from))[0].length;
			return {
				from: n.from,
				to: n.to + e
			};
		}
		e = i.to;
	}
}
function kt({ closing: e, align: t = !0, units: n = 1 }) {
	return (r) => At(r, t, n, e);
}
function At(e, t, n, r, i) {
	let a = e.textAfter, o = a.match(/^\s*/)[0].length, s = r && a.slice(o, o + r.length) == r || i == e.pos + o, c = t ? Ot(e) : null;
	return c ? s ? e.column(c.from) : e.column(c.to) : e.baseIndent + (s ? 0 : e.unit * n);
}
var jt = (e) => e.baseIndent;
function Mt({ except: e, units: t = 1 } = {}) {
	return (n) => {
		let r = e && e.test(n.textAfter);
		return n.baseIndent + (r ? 0 : t * n.unit);
	};
}
var Nt = 200;
function Pt() {
	return r.transactionFilter.of((e) => {
		if (!e.docChanged || !e.isUserEvent("input.type") && !e.isUserEvent("input.complete")) return e;
		let t = e.startState.languageDataAt("indentOnInput", e.startState.selection.main.head);
		if (!t.length) return e;
		let n = e.newDoc, { head: r } = e.newSelection.main, i = n.lineAt(r);
		if (r > i.from + Nt) return e;
		let a = n.sliceString(i.from, r);
		if (!t.some((e) => e.test(a))) return e;
		let { state: o } = e, s = -1, c = [];
		for (let { head: e } of o.selection.ranges) {
			let t = o.doc.lineAt(e);
			if (t.from == s) continue;
			s = t.from;
			let n = vt(o, t.from);
			if (n == null) continue;
			let r = /^\s*/.exec(t.text)[0], i = _t(o, n);
			r != i && c.push({
				from: t.from,
				to: t.from + r.length,
				insert: i
			});
		}
		return c.length ? [e, {
			changes: c,
			sequential: !0
		}] : e;
	});
}
var Ft = /*@__PURE__*/ i.define(), It = /*@__PURE__*/ new b();
function Lt(e) {
	let t = e.firstChild, n = e.lastChild;
	return t && t.to < n.from ? {
		from: t.to,
		to: n.type.isError ? e.to : n.from
	} : null;
}
function Rt(e, t, n) {
	let r = K(e);
	if (r.length < n) return null;
	let i = r.resolveStack(n, 1), a = null;
	for (let o = i; o; o = o.next) {
		let i = o.node;
		if (i.to <= n || i.from > n) continue;
		if (a && i.from < t) break;
		let s = i.type.prop(It);
		if (s && (i.to < r.length - 50 || r.length == e.doc.length || !zt(i))) {
			let r = s(i, e);
			r && r.from <= n && r.from >= t && r.to > n && (a = r);
		}
	}
	return a;
}
function zt(e) {
	let t = e.lastChild;
	return t && t.to == e.to && t.type.isError;
}
function Bt(e, t, n) {
	for (let r of e.facet(Ft)) {
		let i = r(e, t, n);
		if (i) return i;
	}
	return Rt(e, t, n);
}
function Vt(e, t) {
	let n = t.mapPos(e.from, 1), r = t.mapPos(e.to, -1);
	return n >= r ? void 0 : {
		from: n,
		to: r
	};
}
var Ht = /*@__PURE__*/ a.define({ map: Vt }), X = /*@__PURE__*/ a.define({ map: Vt });
function Ut(e) {
	let t = [];
	for (let { head: n } of e.state.selection.ranges) t.some((e) => e.from <= n && e.to >= n) || t.push(e.lineBlockAt(n));
	return t;
}
var Z = /*@__PURE__*/ s.define({
	create() {
		return h.none;
	},
	update(e, t) {
		t.isUserEvent("delete") && t.changes.iterChangedRanges((t, n) => e = Wt(e, t, n)), e = e.map(t.changes);
		let n = [];
		for (let r of t.effects) r.is(Ht) && !Kt(e, r.value.from, r.value.to) ? n.push(r.value) : r.is(X) && (e = e.update({
			filter: (e, t) => r.value.from != e || r.value.to != t,
			filterFrom: r.value.from,
			filterTo: r.value.to
		}));
		if (n.length) {
			let { preparePlaceholder: r } = t.state.facet($t), i = n.map((e) => (r ? h.replace({ widget: new rn(r(t.state, e)) }) : nn).range(e.from, e.to));
			e = e.update({ add: i });
		}
		return t.selection && (e = Wt(e, t.selection.main.head)), e;
	},
	provide: (e) => u.decorations.from(e),
	toJSON(e, t) {
		let n = [];
		return e.between(0, t.doc.length, (e, t) => {
			n.push(e, t);
		}), n;
	},
	fromJSON(e) {
		if (!Array.isArray(e) || e.length % 2) throw RangeError("Invalid JSON for fold state");
		let t = [];
		for (let n = 0; n < e.length;) {
			let r = e[n++], i = e[n++];
			if (typeof r != "number" || typeof i != "number") throw RangeError("Invalid JSON for fold state");
			t.push(nn.range(r, i));
		}
		return h.set(t, !0);
	}
});
function Wt(e, t, n = t) {
	let r = !1;
	return e.between(t, n, (e, i) => {
		e < n && i > t && (r = !0);
	}), r ? e.update({
		filterFrom: t,
		filterTo: n,
		filter: (e, r) => e >= n || r <= t
	}) : e;
}
function Gt(e, t, n) {
	var r;
	let i = null;
	return (r = e.field(Z, !1)) == null || r.between(t, n, (e, t) => {
		(!i || i.from > e) && (i = {
			from: e,
			to: t
		});
	}), i;
}
function Kt(e, t, n) {
	let r = !1;
	return e.between(t, t, (e, i) => {
		e == t && i == n && (r = !0);
	}), r;
}
function qt(e, t) {
	return e.field(Z, !1) ? t : t.concat(a.appendConfig.of(en()));
}
var Jt = (e) => {
	for (let t of Ut(e)) {
		let n = Bt(e.state, t.from, t.to);
		if (n) return e.dispatch({ effects: qt(e.state, [Ht.of(n), Xt(e, n)]) }), !0;
	}
	return !1;
}, Yt = (e) => {
	if (!e.state.field(Z, !1)) return !1;
	let t = [];
	for (let n of Ut(e)) {
		let r = Gt(e.state, n.from, n.to);
		r && t.push(X.of(r), Xt(e, r, !1));
	}
	return t.length && e.dispatch({ effects: t }), t.length > 0;
};
function Xt(e, t, n = !0) {
	let r = e.state.doc.lineAt(t.from).number, i = e.state.doc.lineAt(t.to).number;
	return u.announce.of(`${e.state.phrase(n ? "Folded lines" : "Unfolded lines")} ${r} ${e.state.phrase("to")} ${i}.`);
}
var Zt = [
	{
		key: "Ctrl-Shift-[",
		mac: "Cmd-Alt-[",
		run: Jt
	},
	{
		key: "Ctrl-Shift-]",
		mac: "Cmd-Alt-]",
		run: Yt
	},
	{
		key: "Ctrl-Alt-[",
		run: (e) => {
			let { state: t } = e, n = [];
			for (let r = 0; r < t.doc.length;) {
				let i = e.lineBlockAt(r), a = Bt(t, i.from, i.to);
				a && n.push(Ht.of(a)), r = (a ? e.lineBlockAt(a.to) : i).to + 1;
			}
			return n.length && e.dispatch({ effects: qt(e.state, n) }), !!n.length;
		}
	},
	{
		key: "Ctrl-Alt-]",
		run: (e) => {
			let t = e.state.field(Z, !1);
			if (!t || !t.size) return !1;
			let n = [];
			return t.between(0, e.state.doc.length, (e, t) => {
				n.push(X.of({
					from: e,
					to: t
				}));
			}), e.dispatch({ effects: n }), !0;
		}
	}
], Qt = {
	placeholderDOM: null,
	preparePlaceholder: null,
	placeholderText: "…"
}, $t = /*@__PURE__*/ i.define({ combine(e) {
	return l(e, Qt);
} });
function en(e) {
	let t = [Z, cn];
	return e && t.push($t.of(e)), t;
}
function tn(e, t) {
	let { state: n } = e, r = n.facet($t), i = (t) => {
		let n = e.lineBlockAt(e.posAtDOM(t.target)), r = Gt(e.state, n.from, n.to);
		r && e.dispatch({ effects: X.of(r) }), t.preventDefault();
	};
	if (r.placeholderDOM) return r.placeholderDOM(e, i, t);
	let a = document.createElement("span");
	return a.textContent = r.placeholderText, a.setAttribute("aria-label", n.phrase("folded code")), a.title = n.phrase("unfold"), a.className = "cm-foldPlaceholder", a.onclick = i, a;
}
var nn = /*@__PURE__*/ h.replace({ widget: /*@__PURE__*/ new class extends d {
	toDOM(e) {
		return tn(e, null);
	}
}() }), rn = class extends d {
	constructor(e) {
		super(), this.value = e;
	}
	eq(e) {
		return this.value == e.value;
	}
	toDOM(e) {
		return tn(e, this.value);
	}
}, an = {
	openText: "⌄",
	closedText: "›",
	markerDOM: null,
	domEventHandlers: {},
	foldingChanged: () => !1
}, on = class extends m {
	constructor(e, t) {
		super(), this.config = e, this.open = t;
	}
	eq(e) {
		return this.config == e.config && this.open == e.open;
	}
	toDOM(e) {
		if (this.config.markerDOM) return this.config.markerDOM(this.open);
		let t = document.createElement("span");
		return t.textContent = this.open ? this.config.openText : this.config.closedText, t.title = e.state.phrase(this.open ? "Fold line" : "Unfold line"), t;
	}
};
function sn(e = {}) {
	let n = {
		...an,
		...e
	}, r = new on(n, !0), i = new on(n, !1), a = g.fromClass(class {
		constructor(e) {
			this.from = e.viewport.from, this.markers = this.buildMarkers(e);
		}
		update(e) {
			(e.docChanged || e.viewportChanged || e.startState.facet(J) != e.state.facet(J) || e.startState.field(Z, !1) != e.state.field(Z, !1) || K(e.startState) != K(e.state) || n.foldingChanged(e)) && (this.markers = this.buildMarkers(e.view));
		}
		buildMarkers(e) {
			let t = new o();
			for (let n of e.viewportLineBlocks) {
				let a = Gt(e.state, n.from, n.to) ? i : Bt(e.state, n.from, n.to) ? r : null;
				a && t.add(n.from, n.from, a);
			}
			return t.finish();
		}
	}), { domEventHandlers: s } = n;
	return [
		a,
		f({
			class: "cm-foldGutter",
			markers(e) {
				return e.plugin(a)?.markers || t.empty;
			},
			initialSpacer() {
				return new on(n, !1);
			},
			domEventHandlers: {
				...s,
				click: (e, t, n) => {
					if (s.click && s.click(e, t, n)) return !0;
					let r = Gt(e.state, t.from, t.to);
					if (r) return e.dispatch({ effects: X.of(r) }), !0;
					let i = Bt(e.state, t.from, t.to);
					return i ? (e.dispatch({ effects: Ht.of(i) }), !0) : !1;
				}
			}
		}),
		en()
	];
}
var cn = /*@__PURE__*/ u.baseTheme({
	".cm-foldPlaceholder": {
		backgroundColor: "#eee",
		border: "1px solid #ddd",
		color: "#888",
		borderRadius: ".2em",
		margin: "0 1px",
		padding: "0 1px",
		cursor: "pointer"
	},
	".cm-foldGutter span": {
		padding: "0 1px",
		cursor: "pointer"
	}
}), ln = class t {
	constructor(t, n) {
		this.specs = t;
		let r;
		function i(t) {
			let n = e.newName();
			return (r ||= Object.create(null))["." + n] = t, n;
		}
		let a = typeof n.all == "string" ? n.all : n.all ? i(n.all) : void 0, o = n.scope;
		this.scope = o instanceof G ? (e) => e.prop(W) == o.data : o ? (e) => e == o : void 0, this.style = He(t.map((e) => ({
			tag: e.tag,
			class: e.class || i(Object.assign({}, e, { tag: null }))
		})), { all: a }).style, this.module = r ? new e(r) : null, this.themeType = n.themeType;
	}
	static define(e, n) {
		return new t(e, n || {});
	}
}, un = /*@__PURE__*/ i.define(), dn = /*@__PURE__*/ i.define({ combine(e) {
	return e.length ? [e[0]] : null;
} });
function fn(e) {
	let t = e.facet(un);
	return t.length ? t : e.facet(dn);
}
function pn(e, t) {
	let n = [hn], r;
	return e instanceof ln && (e.module && n.push(u.styleModule.of(e.module)), r = e.themeType), t?.fallback ? n.push(dn.of(e)) : r ? n.push(un.computeN([u.darkTheme], (t) => t.facet(u.darkTheme) == (r == "dark") ? [e] : [])) : n.push(un.of(e)), n;
}
var mn = class {
	constructor(e) {
		this.markCache = Object.create(null), this.tree = K(e.state), this.decorations = this.buildDeco(e, fn(e.state)), this.decoratedTo = e.viewport.to;
	}
	update(e) {
		let t = K(e.state), n = fn(e.state), r = n != fn(e.startState), { viewport: i } = e.view, a = e.changes.mapPos(this.decoratedTo, 1);
		t.length < i.to && !r && t.type == this.tree.type && a >= i.to ? (this.decorations = this.decorations.map(e.changes), this.decoratedTo = a) : (t != this.tree || e.viewportChanged || r) && (this.tree = t, this.decorations = this.buildDeco(e.view, n), this.decoratedTo = i.to);
	}
	buildDeco(e, t) {
		if (!t || !this.tree.length) return h.none;
		let n = new o();
		for (let { from: r, to: i } of e.visibleRanges) We(this.tree, t, (e, t, r) => {
			n.add(e, t, this.markCache[r] || (this.markCache[r] = h.mark({ class: r })));
		}, r, i);
		return n.finish();
	}
}, hn = /*@__PURE__*/ _.high(/*@__PURE__*/ g.fromClass(mn, { decorations: (e) => e.decorations })), gn = /*@__PURE__*/ ln.define([
	{
		tag: U.meta,
		color: "#404740"
	},
	{
		tag: U.link,
		textDecoration: "underline"
	},
	{
		tag: U.heading,
		textDecoration: "underline",
		fontWeight: "bold"
	},
	{
		tag: U.emphasis,
		fontStyle: "italic"
	},
	{
		tag: U.strong,
		fontWeight: "bold"
	},
	{
		tag: U.strikethrough,
		textDecoration: "line-through"
	},
	{
		tag: U.keyword,
		color: "#708"
	},
	{
		tag: [
			U.atom,
			U.bool,
			U.url,
			U.contentSeparator,
			U.labelName
		],
		color: "#219"
	},
	{
		tag: [U.literal, U.inserted],
		color: "#164"
	},
	{
		tag: [U.string, U.deleted],
		color: "#a11"
	},
	{
		tag: [
			U.regexp,
			U.escape,
			/*@__PURE__*/ U.special(U.string)
		],
		color: "#e40"
	},
	{
		tag: /*@__PURE__*/ U.definition(U.variableName),
		color: "#00f"
	},
	{
		tag: /*@__PURE__*/ U.local(U.variableName),
		color: "#30a"
	},
	{
		tag: [U.typeName, U.namespace],
		color: "#085"
	},
	{
		tag: U.className,
		color: "#167"
	},
	{
		tag: [/*@__PURE__*/ U.special(U.variableName), U.macroName],
		color: "#256"
	},
	{
		tag: /*@__PURE__*/ U.definition(U.propertyName),
		color: "#00c"
	},
	{
		tag: U.comment,
		color: "#940"
	},
	{
		tag: U.invalid,
		color: "#f00"
	}
]), _n = /*@__PURE__*/ u.baseTheme({
	"&.cm-focused .cm-matchingBracket": { backgroundColor: "#328c8252" },
	"&.cm-focused .cm-nonmatchingBracket": { backgroundColor: "#bb555544" }
}), vn = 1e4, yn = "()[]{}", bn = /*@__PURE__*/ i.define({ combine(e) {
	return l(e, {
		afterCursor: !0,
		brackets: yn,
		maxScanDistance: vn,
		renderMatch: Cn
	});
} }), xn = /*@__PURE__*/ h.mark({ class: "cm-matchingBracket" }), Sn = /*@__PURE__*/ h.mark({ class: "cm-nonmatchingBracket" });
function Cn(e) {
	let t = [], n = e.matched ? xn : Sn;
	return t.push(n.range(e.start.from, e.start.to)), e.end && t.push(n.range(e.end.from, e.end.to)), t;
}
function wn(e) {
	let t = [], n = e.facet(bn);
	for (let r of e.selection.ranges) {
		if (!r.empty) continue;
		let i = Q(e, r.head, -1, n) || r.head > 0 && Q(e, r.head - 1, 1, n) || n.afterCursor && (Q(e, r.head, 1, n) || r.head < e.doc.length && Q(e, r.head + 1, -1, n));
		i && (t = t.concat(n.renderMatch(i, e)));
	}
	return h.set(t, !0);
}
var Tn = [/* @__PURE__ */ g.fromClass(class {
	constructor(e) {
		this.paused = !1, this.decorations = wn(e.state);
	}
	update(e) {
		(e.docChanged || e.selectionSet || this.paused) && (e.view.composing ? (this.decorations = this.decorations.map(e.changes), this.paused = !0) : (this.decorations = wn(e.state), this.paused = !1));
	}
}, { decorations: (e) => e.decorations }), _n];
function En(e = {}) {
	return [bn.of(e), Tn];
}
var Dn = /*@__PURE__*/ new b();
function On(e, t, n) {
	let r = e.prop(t < 0 ? b.openedBy : b.closedBy);
	if (r) return r;
	if (e.name.length == 1) {
		let r = n.indexOf(e.name);
		if (r > -1 && r % 2 == +(t < 0)) return [n[r + t]];
	}
	return null;
}
function kn(e) {
	let t = e.type.prop(Dn);
	return t ? t(e.node) : e;
}
function Q(e, t, n, r = {}) {
	let i = r.maxScanDistance || vn, a = r.brackets || yn, o = K(e), s = o.resolveInner(t, n);
	for (let r = s; r; r = r.parent) {
		let i = On(r.type, n, a);
		if (i && r.from < r.to) {
			let o = kn(r);
			if (o && (n > 0 ? t >= o.from && t < o.to : t > o.from && t <= o.to)) return An(e, t, n, r, o, i, a);
		}
	}
	return jn(e, t, n, o, s.type, i, a);
}
function An(e, t, n, r, i, a, o) {
	let s = r.parent, c = {
		from: i.from,
		to: i.to
	}, l = 0, u = s?.cursor();
	if (u && (n < 0 ? u.childBefore(r.from) : u.childAfter(r.to))) do
		if (n < 0 ? u.to <= r.from : u.from >= r.to) {
			if (l == 0 && a.indexOf(u.type.name) > -1 && u.from < u.to) {
				let e = kn(u);
				return {
					start: c,
					end: e ? {
						from: e.from,
						to: e.to
					} : void 0,
					matched: !0
				};
			}
			if (On(u.type, n, o)) l++;
			else if (On(u.type, -n, o)) {
				if (l == 0) {
					let e = kn(u);
					return {
						start: c,
						end: e && e.from < e.to ? {
							from: e.from,
							to: e.to
						} : void 0,
						matched: !1
					};
				}
				l--;
			}
		}
	while (n < 0 ? u.prevSibling() : u.nextSibling());
	return {
		start: c,
		matched: !1
	};
}
function jn(e, t, n, r, i, a, o) {
	if (n < 0 ? !t : t == e.doc.length) return null;
	let s = n < 0 ? e.sliceDoc(t - 1, t) : e.sliceDoc(t, t + 1), c = o.indexOf(s);
	if (c < 0 || c % 2 == 0 != n > 0) return null;
	let l = {
		from: n < 0 ? t - 1 : t,
		to: n > 0 ? t + 1 : t
	}, u = e.doc.iterRange(t, n > 0 ? e.doc.length : 0), d = 0;
	for (let e = 0; !u.next().done && e <= a;) {
		let a = u.value;
		n < 0 && (e += a.length);
		let s = t + e * n;
		for (let e = n > 0 ? 0 : a.length - 1, t = n > 0 ? a.length : -1; e != t; e += n) {
			let t = o.indexOf(a[e]);
			if (!(t < 0 || r.resolveInner(s + e, 1).type != i)) {
				if (t % 2 == 0 == n > 0) d++;
				else if (d == 1) return {
					start: l,
					end: {
						from: s + e,
						to: s + e + 1
					},
					matched: t >> 1 == c >> 1
				};
				else d--;
			}
		}
		n > 0 && (e += a.length);
	}
	return u.done ? {
		start: l,
		matched: !1
	} : null;
}
function Mn(e) {
	return {
		name: e.name || "",
		token: e.token,
		blankLine: e.blankLine || (() => {}),
		startState: e.startState || (() => !0),
		copyState: e.copyState || Nn,
		indent: e.indent || (() => null),
		languageData: e.languageData || {},
		tokenTable: e.tokenTable || Vn,
		mergeTokens: e.mergeTokens !== !1
	};
}
function Nn(e) {
	if (typeof e != "object") return e;
	let t = {};
	for (let n in e) {
		let r = e[n];
		t[n] = r instanceof Array ? r.slice() : r;
	}
	return t;
}
var Pn = /*@__PURE__*/ new WeakMap(), Fn = class e extends G {
	constructor(e) {
		let t = nt(e.languageData), n = Mn(e), r, i = new class extends ve {
			createParse(e, t, n) {
				return new zn(r, e, t, n);
			}
		}();
		super(t, i, [], e.name), this.topNode = Xn(t, this), r = this, this.streamParser = n, this.stateAfter = new b({ perNode: !0 }), this.tokenTable = e.tokenTable ? new Kn(n.tokenTable) : qn;
	}
	static define(t) {
		return new e(t);
	}
	getIndent(e) {
		let t, { overrideIndentation: n } = e.options;
		n && (t = Pn.get(e.state), t != null && t < e.pos - 1e4 && (t = void 0));
		let r = In(this, e.node.tree, e.node.from, e.node.from, t ?? e.pos), i, a;
		if (r ? (a = r.state, i = r.pos + 1) : (a = this.streamParser.startState(e.unit), i = e.node.from), e.pos - i > 1e4) return null;
		for (; i < e.pos;) {
			let t = e.state.doc.lineAt(i), r = Math.min(e.pos, t.to);
			if (t.length) {
				let i = n ? n(t.from) : -1, o = new tt(t.text, e.state.tabSize, e.unit, i < 0 ? void 0 : i);
				for (; o.pos < r - t.from;) Bn(this.streamParser.token, o, a);
			} else this.streamParser.blankLine(a, e.unit);
			if (r == e.pos) break;
			i = t.to + 1;
		}
		let o = e.lineAt(e.pos);
		return n && t == null && Pn.set(e.state, o.from), this.streamParser.indent(a, /^\s*(.*)/.exec(o.text)[1], e);
	}
	get allowsNesting() {
		return !1;
	}
};
function In(e, t, n, r, i) {
	let a = n >= r && n + t.length <= i && t.prop(e.stateAfter);
	if (a) return {
		state: e.streamParser.copyState(a),
		pos: n + t.length
	};
	for (let a = t.children.length - 1; a >= 0; a--) {
		let o = t.children[a], s = n + t.positions[a], c = o instanceof D && s < i && In(e, o, s, r, i);
		if (c) return c;
	}
	return null;
}
function Ln(e, t, n, r, i) {
	if (i && n <= 0 && r >= t.length) return t;
	!i && n == 0 && t.type == e.topNode && (i = !0);
	for (let a = t.children.length - 1; a >= 0; a--) {
		let o = t.positions[a], s = t.children[a], c;
		if (o < r && s instanceof D) {
			if (!(c = Ln(e, s, n - o, r - o, i))) break;
			return i ? new D(t.type, t.children.slice(0, a).concat(c), t.positions.slice(0, a + 1), o + c.length) : c;
		}
	}
	return null;
}
function Rn(e, t, n, r, i) {
	for (let i of t) {
		let t = i.from + (i.openStart ? 25 : 0), a = i.to - (i.openEnd ? 25 : 0), o = t <= n && a > n && In(e, i.tree, 0 - i.offset, n, a), s;
		if (o && o.pos <= r && (s = Ln(e, i.tree, n + i.offset, o.pos + i.offset, !1))) return {
			state: o.state,
			tree: s
		};
	}
	return {
		state: e.streamParser.startState(i ? Y(i) : 4),
		tree: D.empty
	};
}
var zn = class {
	constructor(e, t, n, r) {
		this.lang = e, this.input = t, this.fragments = n, this.ranges = r, this.stoppedAt = null, this.chunks = [], this.chunkPos = [], this.chunk = [], this.chunkReused = void 0, this.rangeIndex = 0, this.to = r[r.length - 1].to;
		let i = st.get(), a = r[0].from, { state: o, tree: s } = Rn(e, n, a, this.to, i?.state);
		this.state = o, this.parsedPos = this.chunkStart = a + s.length;
		for (let e = 0; e < s.children.length; e++) this.chunks.push(s.children[e]), this.chunkPos.push(s.positions[e]);
		i && this.parsedPos < i.viewport.from - 1e5 && r.some((e) => e.from <= i.viewport.from && e.to >= i.viewport.from) && (this.state = this.lang.streamParser.startState(Y(i.state)), i.skipUntilInView(this.parsedPos, i.viewport.from), this.parsedPos = i.viewport.from), this.moveRangeIndex();
	}
	advance() {
		let e = st.get(), t = this.stoppedAt == null ? this.to : Math.min(this.to, this.stoppedAt), n = Math.min(t, this.chunkStart + 512);
		for (e && (n = Math.min(n, e.viewport.to)); this.parsedPos < n;) this.parseLine(e);
		return this.chunkStart < this.parsedPos && this.finishChunk(), this.parsedPos >= t ? this.finish() : e && this.parsedPos >= e.viewport.to ? (e.skipUntilInView(this.parsedPos, t), this.finish()) : null;
	}
	stopAt(e) {
		this.stoppedAt = e;
	}
	lineAfter(e) {
		let t = this.input.chunk(e);
		if (this.input.lineChunks) t == "\n" && (t = "");
		else {
			let e = t.indexOf("\n");
			e > -1 && (t = t.slice(0, e));
		}
		return e + t.length <= this.to ? t : t.slice(0, this.to - e);
	}
	nextLine() {
		let e = this.parsedPos, t = this.lineAfter(e), n = e + t.length;
		for (let e = this.rangeIndex;;) {
			let r = this.ranges[e].to;
			if (r >= n || (t = t.slice(0, r - (n - t.length)), e++, e == this.ranges.length)) break;
			let i = this.ranges[e].from, a = this.lineAfter(i);
			t += a, n = i + a.length;
		}
		return {
			line: t,
			end: n
		};
	}
	skipGapsTo(e, t, n) {
		for (;;) {
			let r = this.ranges[this.rangeIndex].to, i = e + t;
			if (n > 0 ? r > i : r >= i) break;
			let a = this.ranges[++this.rangeIndex].from;
			t += a - r;
		}
		return t;
	}
	moveRangeIndex() {
		for (; this.ranges[this.rangeIndex].to < this.parsedPos;) this.rangeIndex++;
	}
	emitToken(e, t, n, r) {
		let i = 4;
		if (this.ranges.length > 1) {
			r = this.skipGapsTo(t, r, 1), t += r;
			let e = this.chunk.length;
			r = this.skipGapsTo(n, r, -1), n += r, i += this.chunk.length - e;
		}
		let a = this.chunk.length - 4;
		return this.lang.streamParser.mergeTokens && i == 4 && a >= 0 && this.chunk[a] == e && this.chunk[a + 2] == t ? this.chunk[a + 2] = n : this.chunk.push(e, t, n, i), r;
	}
	parseLine(e) {
		let { line: t, end: n } = this.nextLine(), r = 0, { streamParser: i } = this.lang, a = new tt(t, e ? e.state.tabSize : 4, e ? Y(e.state) : 2);
		if (a.eol()) i.blankLine(this.state, a.indentUnit);
		else for (; !a.eol();) {
			let e = Bn(i.token, a, this.state);
			if (e && (r = this.emitToken(this.lang.tokenTable.resolve(e), this.parsedPos + a.start, this.parsedPos + a.pos, r)), a.start > 1e4) break;
		}
		this.parsedPos = n, this.moveRangeIndex(), this.parsedPos < this.to && this.parsedPos++;
	}
	finishChunk() {
		let e = D.build({
			buffer: this.chunk,
			start: this.chunkStart,
			length: this.parsedPos - this.chunkStart,
			nodeSet: Hn,
			topID: 0,
			maxBufferLength: 512,
			reused: this.chunkReused
		});
		e = new D(e.type, e.children, e.positions, e.length, [[this.lang.stateAfter, this.lang.streamParser.copyState(this.state)]]), this.chunks.push(e), this.chunkPos.push(this.chunkStart - this.ranges[0].from), this.chunk = [], this.chunkReused = void 0, this.chunkStart = this.parsedPos;
	}
	finish() {
		return new D(this.lang.topNode, this.chunks, this.chunkPos, this.parsedPos - this.ranges[0].from).balance();
	}
};
function Bn(e, t, n) {
	t.start = t.pos;
	for (let r = 0; r < 10; r++) {
		let r = e(t, n);
		if (t.pos > t.start) return r;
	}
	throw Error("Stream parser failed to advance stream.");
}
var Vn = /*@__PURE__*/ Object.create(null), $ = [C.none], Hn = /*@__PURE__*/ new te($), Un = [], Wn = /*@__PURE__*/ Object.create(null), Gn = /*@__PURE__*/ Object.create(null);
for (let [e, t] of [
	["variable", "variableName"],
	["variable-2", "variableName.special"],
	["string-2", "string.special"],
	["def", "variableName.definition"],
	["tag", "tagName"],
	["attribute", "attributeName"],
	["type", "typeName"],
	["builtin", "variableName.standard"],
	["qualifier", "modifier"],
	["error", "invalid"],
	["header", "heading"],
	["property", "propertyName"]
]) Gn[e] = /*@__PURE__*/ Yn(Vn, t);
var Kn = class {
	constructor(e) {
		this.extra = e, this.table = Object.assign(Object.create(null), Gn);
	}
	resolve(e) {
		return e ? this.table[e] || (this.table[e] = Yn(this.extra, e)) : 0;
	}
}, qn = /*@__PURE__*/ new Kn(Vn);
function Jn(e, t) {
	Un.indexOf(e) > -1 || (Un.push(e), console.warn(t));
}
function Yn(e, t) {
	let n = [];
	for (let r of t.split(" ")) {
		let t = [];
		for (let n of r.split(".")) {
			let r = e[n] || U[n];
			r ? typeof r == "function" ? t.length ? t = t.map(r) : Jn(n, `Modifier ${n} used at start of tag`) : t.length ? Jn(n, `Tag ${n} used as modifier`) : t = Array.isArray(r) ? r : [r] : Jn(n, `Unknown highlighting tag ${n}`);
		}
		for (let e of t) n.push(e);
	}
	if (!n.length) return 0;
	let r = t.replace(/ /g, "_"), i = r + " " + n.map((e) => e.id), a = Wn[i];
	if (a) return a.id;
	let o = Wn[i] = C.define({
		id: $.length,
		name: r,
		props: [Be({ [r]: n })]
	});
	return $.push(o), o.id;
}
function Xn(e, t) {
	let n = C.define({
		id: $.length,
		name: "Document",
		props: [W.add(() => e), bt.add(() => (e) => t.getIndent(e))],
		top: !0
	});
	return $.push(n), n;
}
p.RTL, p.LTR;
export { pn as A, _e as B, bt as C, W as D, gt as E, ee as F, D as H, E as I, b as L, N as M, Be as N, Q as O, U as P, te as R, vt as S, _t as T, be as U, ve as V, Lt as _, mt as a, Ft as b, Fn as c, Mt as d, gn as f, sn as g, jt as h, G as i, K as j, rt as k, En as l, kt as m, yt as n, pt as o, nt as p, at as r, st as s, ln as t, Dn as u, Zt as v, Pt as w, Y as x, It as y, C as z };
