// node_modules/.pnpm/@vue+shared@3.5.44/node_modules/@vue/shared/dist/shared.esm-bundler.js
// @__NO_SIDE_EFFECTS__
function e(e) {
	let t = /* @__PURE__ */ Object.create(null);
	for (let n of e.split(",")) t[n] = 1;
	return (e) => e in t;
}
var t = {}, n = [], r = () => {}, i = () => !1, a = (e) => e.charCodeAt(0) === 111 && e.charCodeAt(1) === 110 && (e.charCodeAt(2) > 122 || e.charCodeAt(2) < 97), o = (e) => e.startsWith("onUpdate:"), s = Object.assign, c = (e, t) => {
	let n = e.indexOf(t);
	n > -1 && e.splice(n, 1);
}, l = Object.prototype.hasOwnProperty, u = (e, t) => l.call(e, t), d = Array.isArray, f = (e) => x(e) === "[object Map]", p = (e) => x(e) === "[object Set]", m = (e) => x(e) === "[object Date]", h = (e) => typeof e == "function", g = (e) => typeof e == "string", _ = (e) => typeof e == "symbol", v = (e) => typeof e == "object" && !!e, y = (e) => (v(e) || h(e)) && h(e.then) && h(e.catch), b = Object.prototype.toString, x = (e) => b.call(e), S = (e) => x(e).slice(8, -1), C = (e) => x(e) === "[object Object]", w = (e) => g(e) && e !== "NaN" && e[0] !== "-" && "" + parseInt(e, 10) === e, T = /* @__PURE__ */ e(",key,ref,ref_for,ref_key,onVnodeBeforeMount,onVnodeMounted,onVnodeBeforeUpdate,onVnodeUpdated,onVnodeBeforeUnmount,onVnodeUnmounted"), ee = (e) => {
	let t = /* @__PURE__ */ Object.create(null);
	return ((n) => t[n] || (t[n] = e(n)));
}, te = /-\w/g, E = ee((e) => e.replace(te, (e) => e.slice(1).toUpperCase())), ne = /\B([A-Z])/g, D = ee((e) => e.replace(ne, "-$1").toLowerCase()), re = ee((e) => e.charAt(0).toUpperCase() + e.slice(1)), ie = ee((e) => e ? `on${re(e)}` : ""), O = (e, t) => !Object.is(e, t), ae = (e, ...t) => {
	for (let n = 0; n < e.length; n++) e[n](...t);
}, k = (e, t, n, r = !1) => {
	Object.defineProperty(e, t, {
		configurable: !0,
		enumerable: !1,
		writable: r,
		value: n
	});
}, oe = (e) => {
	let t = parseFloat(e);
	return isNaN(t) ? e : t;
}, se, ce = () => se ||= typeof globalThis < "u" ? globalThis : typeof self < "u" ? self : typeof window < "u" ? window : typeof global < "u" ? global : {};
function le(e) {
	if (d(e)) {
		let t = {};
		for (let n = 0; n < e.length; n++) {
			let r = e[n], i = g(r) ? pe(r) : le(r);
			if (i) for (let e in i) t[e] = i[e];
		}
		return t;
	}
	if (g(e) || v(e)) return e;
}
var ue = /;(?![^(]*\))/g, de = /:([^]+)/, fe = /"(?:[^"\\]|\\[^])*"|'(?:[^'\\]|\\[^])*'|\\[^]|\/\*[^]*?\*\//g;
function pe(e) {
	let t = {};
	return e.replace(fe, (e) => e.startsWith("/*") ? "" : e).split(ue).forEach((e) => {
		if (e) {
			let n = e.split(de);
			n.length > 1 && (t[n[0].trim()] = n[1].trim());
		}
	}), t;
}
function me(e) {
	let t = "";
	if (g(e)) t = e;
	else if (d(e)) for (let n = 0; n < e.length; n++) {
		let r = me(e[n]);
		r && (t += r + " ");
	}
	else if (v(e)) for (let n in e) e[n] && (t += n + " ");
	return t.trim();
}
var he = /* @__PURE__ */ e("itemscope,allowfullscreen,formnovalidate,ismap,nomodule,novalidate,playsinline,readonly");
function ge(e) {
	return !!e || e === "";
}
function _e(e, t, n) {
	if (e.length !== t.length) return !1;
	let r = !0;
	for (let i = 0; r && i < e.length; i++) r = xe(e[i], t[i], n);
	return r;
}
function ve(e, t, n) {
	if (e.size !== t.size) return !1;
	let r = Array.from(t), i = new Uint8Array(r.length);
	for (let t of e) {
		let e = -1;
		for (let a = 0; a < r.length; a++) if (!i[a] && xe(t, r[a], n)) {
			e = a;
			break;
		}
		if (e < 0) return !1;
		i[e] = 1;
	}
	return !0;
}
function ye(e, t, n) {
	let r = f(e), i = f(t);
	if (r || i || (r = p(e), i = p(t), r || i)) return r && i ? ve(e, t, n) : !1;
	if (Object.keys(e).length !== Object.keys(t).length) return !1;
	for (let r in e) {
		let i = e.hasOwnProperty(r), a = t.hasOwnProperty(r);
		if (i && !a || !i && a || !xe(e[r], t[r], n)) return !1;
	}
	return String(e) === String(t);
}
function be(e, t, n, r) {
	n ||= [/* @__PURE__ */ new Map(), /* @__PURE__ */ new Map()];
	let [i, a] = n;
	if (i.has(e) || a.has(t)) return i.get(e) === t && a.get(t) === e;
	i.set(e, t), a.set(t, e);
	let o = r(e, t, n);
	return i.delete(e), a.delete(t), o;
}
function xe(e, t, n) {
	if (e === t) return !0;
	let r = m(e), i = m(t);
	return r || i ? r && i ? e.getTime() === t.getTime() : !1 : (r = _(e), i = _(t), r || i ? e === t : (r = d(e), i = d(t), r || i ? r && i ? be(e, t, n, _e) : !1 : (r = v(e), i = v(t), r || i ? !r || !i ? !1 : be(e, t, n, ye) : String(e) === String(t))));
}
// node_modules/.pnpm/@vue+reactivity@3.5.44/node_modules/@vue/reactivity/dist/reactivity.esm-bundler.js
var A, Se = class {
	constructor(e = !1) {
		this.detached = e, this._active = !0, this._on = 0, this.effects = [], this.cleanups = [], this._isPaused = !1, this._warnOnRun = !0, this.__v_skip = !0, !e && A && (A.active ? (this.parent = A, this.index = (A.scopes || (A.scopes = [])).push(this) - 1) : (this._active = !1, this._warnOnRun = !1));
	}
	get active() {
		return this._active;
	}
	pause() {
		if (this._active) {
			this._isPaused = !0;
			let e, t;
			if (this.scopes) {
				let n = this.scopes.slice();
				for (e = 0, t = n.length; e < t; e++) n[e].pause();
			}
			for (e = 0, t = this.effects.length; e < t; e++) this.effects[e].pause();
		}
	}
	resume() {
		if (this._active && this._isPaused) {
			this._isPaused = !1;
			let e, t;
			if (this.scopes) {
				let n = this.scopes.slice();
				for (e = 0, t = n.length; e < t; e++) n[e].resume();
			}
			let n = this.effects.slice();
			for (e = 0, t = n.length; e < t; e++) n[e].resume();
		}
	}
	run(e) {
		if (this._active) {
			let t = A;
			try {
				return A = this, e();
			} finally {
				A = t;
			}
		}
	}
	on() {
		++this._on === 1 && (this.prevScope = A, A = this);
	}
	off() {
		if (this._on > 0 && --this._on === 0) {
			if (A === this) A = this.prevScope;
			else {
				let e = A;
				for (; e;) {
					if (e.prevScope === this) {
						e.prevScope = this.prevScope;
						break;
					}
					e = e.prevScope;
				}
			}
			this.prevScope = void 0;
		}
	}
	stop(e) {
		if (this._active) {
			this._active = !1;
			let t, n;
			for (t = 0, n = this.effects.length; t < n; t++) this.effects[t].stop();
			for (this.effects.length = 0, t = 0, n = this.cleanups.length; t < n; t++) this.cleanups[t]();
			if (this.cleanups.length = 0, this.scopes) {
				let e = this.scopes.slice();
				for (t = 0, n = e.length; t < n; t++) e[t].stop(!0);
				this.scopes.length = 0;
			}
			if (!this.detached && this.parent && !e) {
				let e = this.parent.scopes.pop();
				e && e !== this && (this.parent.scopes[this.index] = e, e.index = this.index);
			}
			this.parent = void 0;
		}
	}
};
function Ce() {
	return A;
}
var j, we = /* @__PURE__ */ new WeakSet(), Te = class {
	constructor(e) {
		this.fn = e, this.deps = void 0, this.depsTail = void 0, this.flags = 5, this.next = void 0, this.cleanup = void 0, this.scheduler = void 0, A && (A.active ? A.effects.push(this) : this.flags &= -2);
	}
	pause() {
		this.flags |= 64;
	}
	resume() {
		this.flags & 64 && (this.flags &= -65, we.has(this) && (we.delete(this), this.trigger()));
	}
	notify() {
		this.flags & 2 && !(this.flags & 32) || this.flags & 8 || ke(this);
	}
	run() {
		if (!(this.flags & 1)) return this.fn();
		this.flags |= 2, ze(this), Me(this);
		let e = j, t = M;
		j = this, M = !0;
		try {
			return this.fn();
		} finally {
			Ne(this), j = e, M = t, this.flags &= -3;
		}
	}
	stop() {
		if (this.flags & 1) {
			for (let e = this.deps; e; e = e.nextDep) Ie(e);
			this.deps = this.depsTail = void 0, ze(this), this.onStop && this.onStop(), this.flags &= -2;
		}
	}
	trigger() {
		this.flags & 64 ? we.add(this) : this.scheduler ? this.scheduler() : this.runIfDirty();
	}
	runIfDirty() {
		Pe(this) && this.run();
	}
	get dirty() {
		return Pe(this);
	}
}, Ee = 0, De, Oe;
function ke(e, t = !1) {
	e.flags |= 8, t ? (e.next = Oe, Oe = e) : (e.next = De, De = e);
}
function Ae() {
	Ee++;
}
function je() {
	if (--Ee > 0) return;
	if (Oe) {
		let e = Oe;
		for (Oe = void 0; e;) {
			let t = e.next;
			e.next = void 0, e.flags &= -9, e = t;
		}
	}
	let e;
	for (; De;) {
		let t = De;
		for (De = void 0; t;) {
			let n = t.next;
			if (t.next = void 0, t.flags &= -9, t.flags & 1) try {
				t.trigger();
			} catch (t) {
				e ||= t;
			}
			t = n;
		}
	}
	if (e) throw e;
}
function Me(e) {
	for (let t = e.deps; t; t = t.nextDep) t.version = -1, t.prevActiveLink = t.dep.activeLink, t.dep.activeLink = t;
}
function Ne(e) {
	let t, n = e.depsTail, r = n;
	for (; r;) {
		let e = r.prevDep;
		r.version === -1 ? (r === n && (n = e), Ie(r), Le(r)) : t = r, r.dep.activeLink = r.prevActiveLink, r.prevActiveLink = void 0, r = e;
	}
	e.deps = t, e.depsTail = n;
}
function Pe(e) {
	for (let t = e.deps; t; t = t.nextDep) if (t.dep.version !== t.version || t.dep.computed && (Fe(t.dep.computed) || t.dep.version !== t.version)) return !0;
	return !!e._dirty;
}
function Fe(e) {
	if (e.flags & 4 && !(e.flags & 16) || (e.flags &= -17, e.globalVersion === Be) || (e.globalVersion = Be, !e.isSSR && e.flags & 128 && (!e.deps && !e._dirty || !Pe(e)))) return;
	e.flags |= 2;
	let t = e.dep, n = j, r = M;
	j = e, M = !0;
	try {
		Me(e);
		let n = e.fn(e._value);
		(t.version === 0 || O(n, e._value)) && (e.flags |= 128, e._value = n, t.version++);
	} catch (e) {
		throw t.version++, e;
	} finally {
		j = n, M = r, Ne(e), e.flags &= -3;
	}
}
function Ie(e, t = !1) {
	let { dep: n, prevSub: r, nextSub: i } = e;
	if (r && (r.nextSub = i, e.prevSub = void 0), i && (i.prevSub = r, e.nextSub = void 0), n.subs === e && (n.subs = r, !r && n.computed)) {
		n.computed.flags &= -5;
		for (let e = n.computed.deps; e; e = e.nextDep) Ie(e, !0);
	}
	!t && !--n.sc && n.map && n.map.delete(n.key);
}
function Le(e) {
	let { prevDep: t, nextDep: n } = e;
	t && (t.nextDep = n, e.prevDep = void 0), n && (n.prevDep = t, e.nextDep = void 0);
}
var M = !0, Re = [];
function N() {
	Re.push(M), M = !1;
}
function P() {
	let e = Re.pop();
	M = e === void 0 || e;
}
function ze(e) {
	let { cleanup: t } = e;
	if (e.cleanup = void 0, t) {
		let e = j;
		j = void 0;
		try {
			t();
		} finally {
			j = e;
		}
	}
}
var Be = 0, Ve = class {
	constructor(e, t) {
		this.sub = e, this.dep = t, this.version = t.version, this.nextDep = this.prevDep = this.nextSub = this.prevSub = this.prevActiveLink = void 0;
	}
}, He = class {
	constructor(e) {
		this.computed = e, this.version = 0, this.activeLink = void 0, this.subs = void 0, this.map = void 0, this.key = void 0, this.sc = 0, this.__v_skip = !0;
	}
	track(e) {
		if (!j || !M || j === this.computed) return;
		let t = this.activeLink;
		if (t === void 0 || t.sub !== j) t = this.activeLink = new Ve(j, this), j.deps ? (t.prevDep = j.depsTail, j.depsTail.nextDep = t, j.depsTail = t) : j.deps = j.depsTail = t, Ue(t);
		else if (t.version === -1 && (t.version = this.version, t.nextDep)) {
			let e = t.nextDep;
			e.prevDep = t.prevDep, t.prevDep && (t.prevDep.nextDep = e), t.prevDep = j.depsTail, t.nextDep = void 0, j.depsTail.nextDep = t, j.depsTail = t, j.deps === t && (j.deps = e);
		}
		return t;
	}
	trigger(e) {
		this.version++, Be++, this.notify(e);
	}
	notify(e) {
		Ae();
		try {
			for (let e = this.subs; e; e = e.prevSub) e.sub.notify() && e.sub.dep.notify();
		} finally {
			je();
		}
	}
};
function Ue(e) {
	if (e.dep.sc++, e.sub.flags & 4) {
		let t = e.dep.computed;
		if (t && !e.dep.subs) {
			t.flags |= 20;
			for (let e = t.deps; e; e = e.nextDep) Ue(e);
		}
		let n = e.dep.subs;
		n !== e && (e.prevSub = n, n && (n.nextSub = e)), e.dep.subs = e;
	}
}
var We = /* @__PURE__ */ new WeakMap(), Ge = /* @__PURE__ */ Symbol(""), Ke = /* @__PURE__ */ Symbol(""), qe = /* @__PURE__ */ Symbol("");
function F(e, t, n) {
	if (M && j) {
		let t = We.get(e);
		t || We.set(e, t = /* @__PURE__ */ new Map());
		let r = t.get(n);
		r || (t.set(n, r = new He()), r.map = t, r.key = n), r.track();
	}
}
function Je(e, t, n, r, i, a) {
	let o = We.get(e);
	if (!o) {
		Be++;
		return;
	}
	let s = (e) => {
		e && e.trigger();
	};
	if (Ae(), t === "clear") o.forEach(s);
	else {
		let i = d(e), a = i && w(n);
		if (i && n === "length") {
			let e = Number(r);
			o.forEach((t, n) => {
				(n === "length" || n === qe || !_(n) && n >= e) && s(t);
			});
		} else switch ((n !== void 0 || o.has(void 0)) && s(o.get(n)), a && s(o.get(qe)), t) {
			case "add":
				i ? a && s(o.get("length")) : (s(o.get(Ge)), f(e) && s(o.get(Ke)));
				break;
			case "delete":
				i || (s(o.get(Ge)), f(e) && s(o.get(Ke)));
				break;
			case "set": f(e) && s(o.get(Ge));
		}
	}
	je();
}
function Ye(e) {
	let t = /* @__PURE__ */ L(e);
	return t === e || (F(t, "iterate", qe), /* @__PURE__ */ I(e)) ? t : /* @__PURE__ */ Pt(e) ? /* @__PURE__ */ Nt(e) ? t.map((e) => Lt(R(e))) : t.map(Lt) : t.map(R);
}
function Xe(e) {
	return F(e = /* @__PURE__ */ L(e), "iterate", qe), e;
}
function Ze(e, t) {
	return /* @__PURE__ */ Pt(e) ? Lt(/* @__PURE__ */ Nt(e) ? R(t) : t) : R(t);
}
var Qe = {
	__proto__: null,
	[Symbol.iterator]() {
		return $e(this, Symbol.iterator, (e) => Ze(this, e));
	},
	concat(...e) {
		return Ye(this).concat(...e.map((e) => d(e) ? Ye(e) : e));
	},
	entries() {
		return $e(this, "entries", (e) => (e[1] = Ze(this, e[1]), e));
	},
	every(e, t) {
		return nt(this, "every", e, t, void 0, arguments);
	},
	filter(e, t) {
		return nt(this, "filter", e, t, (e) => e.map((e) => Ze(this, e)), arguments);
	},
	find(e, t) {
		return nt(this, "find", e, t, (e) => Ze(this, e), arguments);
	},
	findIndex(e, t) {
		return nt(this, "findIndex", e, t, void 0, arguments);
	},
	findLast(e, t) {
		return nt(this, "findLast", e, t, (e) => Ze(this, e), arguments);
	},
	findLastIndex(e, t) {
		return nt(this, "findLastIndex", e, t, void 0, arguments);
	},
	forEach(e, t) {
		return nt(this, "forEach", e, t, void 0, arguments);
	},
	includes(...e) {
		return it(this, "includes", e);
	},
	indexOf(...e) {
		return it(this, "indexOf", e);
	},
	join(e) {
		return Ye(this).join(e);
	},
	lastIndexOf(...e) {
		return it(this, "lastIndexOf", e);
	},
	map(e, t) {
		return nt(this, "map", e, t, void 0, arguments);
	},
	pop() {
		return at(this, "pop");
	},
	push(...e) {
		return at(this, "push", e);
	},
	reduce(e, ...t) {
		return rt(this, "reduce", e, t);
	},
	reduceRight(e, ...t) {
		return rt(this, "reduceRight", e, t);
	},
	shift() {
		return at(this, "shift");
	},
	some(e, t) {
		return nt(this, "some", e, t, void 0, arguments);
	},
	splice(...e) {
		return at(this, "splice", e);
	},
	toReversed() {
		return Ye(this).toReversed();
	},
	toSorted(e) {
		return Ye(this).toSorted(e);
	},
	toSpliced(...e) {
		return Ye(this).toSpliced(...e);
	},
	unshift(...e) {
		return at(this, "unshift", e);
	},
	values() {
		return $e(this, "values", (e) => Ze(this, e));
	}
};
function $e(e, t, n) {
	let r = Xe(e), i = r[t], a = i.call(r);
	if (r !== e && !/* @__PURE__ */ I(e)) {
		if (a[Symbol.toStringTag] === "Array Iterator") {
			let e = Object.create(Object.getPrototypeOf(a));
			return e.next = () => et(a.next(), n), i !== tt[t] && Object.defineProperties(e, {
				return: { get() {
					let e = a.return;
					return typeof e == "function" ? e.bind(a) : e;
				} },
				throw: { get() {
					let e = a.throw;
					return typeof e == "function" ? e.bind(a) : e;
				} }
			}), e;
		}
		a._next = a.next, a.next = () => et(a._next(), n);
	}
	return a;
}
function et(e, t) {
	return e.done || (e.value = t(e.value)), e;
}
var tt = Array.prototype;
function nt(e, t, n, r, i, a) {
	let o = Xe(e), s = o !== e && !/* @__PURE__ */ I(e), c = o[t];
	if (c !== tt[t]) {
		let t = c.apply(e, a);
		return s ? R(t) : t;
	}
	let l = n;
	o !== e && (s ? l = function(t, r) {
		return n.call(this, Ze(e, t), r, e);
	} : n.length > 2 && (l = function(t, r) {
		return n.call(this, t, r, e);
	}));
	let u = c.call(o, l, r);
	return s && i ? i(u) : u;
}
function rt(e, t, n, r) {
	let i = Xe(e), a = i !== e && !/* @__PURE__ */ I(e), o = n, s = !1;
	i !== e && (a ? (s = r.length === 0, o = function(t, r, i) {
		return s && (s = !1, t = Ze(e, t)), n.call(this, t, Ze(e, r), i, e);
	}) : n.length > 3 && (o = function(t, r, i) {
		return n.call(this, t, r, i, e);
	}));
	let c = i[t](o, ...r);
	return s ? Ze(e, c) : c;
}
function it(e, t, n) {
	let r = /* @__PURE__ */ L(e);
	F(r, "iterate", qe);
	let i = r[t](...n);
	return (i === -1 || i === !1) && /* @__PURE__ */ Ft(n[0]) ? (n[0] = /* @__PURE__ */ L(n[0]), r[t](...n)) : i;
}
function at(e, t, n = []) {
	N(), Ae();
	let r = (/* @__PURE__ */ L(e))[t].apply(e, n);
	return je(), P(), r;
}
var ot = /* @__PURE__ */ e("__proto__,__v_isRef,__isVue"), st = new Set(/* @__PURE__ */ Object.getOwnPropertyNames(Symbol).filter((e) => e !== "arguments" && e !== "caller").map((e) => Symbol[e]).filter(_));
function ct(e) {
	_(e) || (e = String(e));
	let t = /* @__PURE__ */ L(this);
	return F(t, "has", e), t.hasOwnProperty(e);
}
var lt = class {
	constructor(e = !1, t = !1) {
		this._isReadonly = e, this._isShallow = t;
	}
	get(e, t, n) {
		if (t === "__v_skip") return e.__v_skip;
		let r = this._isReadonly, i = this._isShallow;
		if (t === "__v_isReactive") return !r;
		if (t === "__v_isReadonly") return r;
		if (t === "__v_isShallow") return i;
		if (t === "__v_raw") return n === (r ? i ? Dt : Et : i ? Tt : wt).get(e) || Object.getPrototypeOf(e) === Object.getPrototypeOf(n) ? e : void 0;
		let a = d(e);
		if (!r) {
			let e;
			if (a && (e = Qe[t])) return e;
			if (t === "hasOwnProperty") return ct;
		}
		let o = Reflect.get(e, t, /* @__PURE__ */ z(e) ? e : n);
		if ((_(t) ? st.has(t) : ot(t)) || (r || F(e, "get", t), i)) return o;
		if (/* @__PURE__ */ z(o)) {
			let e = a && w(t) ? o : o.value;
			return r && v(e) ? /* @__PURE__ */ jt(e) : e;
		}
		return v(o) ? r ? /* @__PURE__ */ jt(o) : /* @__PURE__ */ kt(o) : o;
	}
}, ut = class extends lt {
	constructor(e = !1) {
		super(!1, e);
	}
	set(e, t, n, r) {
		let i = e[t], a = d(e) && w(t);
		if (!this._isShallow) {
			let e = /* @__PURE__ */ Pt(i);
			if (!/* @__PURE__ */ I(n) && !/* @__PURE__ */ Pt(n) && (i = /* @__PURE__ */ L(i), n = /* @__PURE__ */ L(n)), !a && /* @__PURE__ */ z(i) && !/* @__PURE__ */ z(n)) return e || (i.value = n), !0;
		}
		let o = a ? Number(t) < e.length : u(e, t), s = Reflect.set(e, t, n, /* @__PURE__ */ z(e) ? e : r);
		return e === /* @__PURE__ */ L(r) && s && (o ? O(n, i) && Je(e, "set", t, n, i) : Je(e, "add", t, n)), s;
	}
	deleteProperty(e, t) {
		let n = u(e, t), r = e[t], i = Reflect.deleteProperty(e, t);
		return i && n && Je(e, "delete", t, void 0, r), i;
	}
	has(e, t) {
		let n = Reflect.has(e, t);
		return _(t) && st.has(t) || F(e, "has", t), n;
	}
	ownKeys(e) {
		return F(e, "iterate", d(e) ? "length" : Ge), Reflect.ownKeys(e);
	}
}, dt = class extends lt {
	constructor(e = !1) {
		super(!0, e);
	}
	set(e, t) {
		return !0;
	}
	deleteProperty(e, t) {
		return !0;
	}
}, ft = /* @__PURE__ */ new ut(), pt = /* @__PURE__ */ new dt(), mt = /* @__PURE__ */ new ut(!0), ht = (e) => e, gt = (e) => Reflect.getPrototypeOf(e);
function _t(e, t, n) {
	return function(...r) {
		let i = this.__v_raw, a = /* @__PURE__ */ L(i), o = f(a), c = e === "entries" || e === Symbol.iterator && o, l = e === "keys" && o, u = i[e](...r), d = n ? ht : t ? Lt : R;
		return !t && F(a, "iterate", l ? Ke : Ge), s(Object.create(u), { next() {
			let { value: e, done: t } = u.next();
			return t ? {
				value: e,
				done: t
			} : {
				value: c ? [d(e[0]), d(e[1])] : d(e),
				done: t
			};
		} });
	};
}
function vt(e) {
	return function(...t) {
		return e === "delete" ? !1 : e === "clear" ? void 0 : this;
	};
}
function yt(e, t) {
	let n = {
		get(n) {
			let r = this.__v_raw, i = /* @__PURE__ */ L(r), a = /* @__PURE__ */ L(n);
			e || (O(n, a) && F(i, "get", n), F(i, "get", a));
			let { has: o } = gt(i), s = t ? ht : e ? Lt : R;
			if (o.call(i, n)) return s(r.get(n));
			if (o.call(i, a)) return s(r.get(a));
			r !== i && r.get(n);
		},
		get size() {
			let t = this.__v_raw;
			return !e && F(/* @__PURE__ */ L(t), "iterate", Ge), t.size;
		},
		has(t) {
			let n = this.__v_raw, r = /* @__PURE__ */ L(n), i = /* @__PURE__ */ L(t);
			return e || (O(t, i) && F(r, "has", t), F(r, "has", i)), t === i ? n.has(t) : n.has(t) || n.has(i);
		},
		forEach(n, r) {
			let i = this, a = i.__v_raw, o = /* @__PURE__ */ L(a), s = t ? ht : e ? Lt : R;
			return !e && F(o, "iterate", Ge), a.forEach((e, t) => n.call(r, s(e), s(t), i));
		}
	};
	return s(n, e ? {
		add: vt("add"),
		set: vt("set"),
		delete: vt("delete"),
		clear: vt("clear")
	} : {
		add(e) {
			let n = /* @__PURE__ */ L(this), r = gt(n), i = /* @__PURE__ */ L(e), a = !t && !/* @__PURE__ */ I(e) && !/* @__PURE__ */ Pt(e) ? i : e;
			return r.has.call(n, a) || O(e, a) && r.has.call(n, e) || O(i, a) && r.has.call(n, i) || (n.add(a), Je(n, "add", a, a)), this;
		},
		set(e, n) {
			t || /* @__PURE__ */ I(n) || /* @__PURE__ */ Pt(n) || (n = /* @__PURE__ */ L(n));
			let r = /* @__PURE__ */ L(this), { has: i, get: a } = gt(r), o = i.call(r, e);
			o ||= (e = /* @__PURE__ */ L(e), i.call(r, e));
			let s = a.call(r, e);
			return r.set(e, n), o ? O(n, s) && Je(r, "set", e, n, s) : Je(r, "add", e, n), this;
		},
		delete(e) {
			let t = /* @__PURE__ */ L(this), { has: n, get: r } = gt(t), i = n.call(t, e);
			i ||= (e = /* @__PURE__ */ L(e), n.call(t, e));
			let a = r ? r.call(t, e) : void 0, o = t.delete(e);
			return i && Je(t, "delete", e, void 0, a), o;
		},
		clear() {
			let e = /* @__PURE__ */ L(this), t = e.size !== 0, n = e.clear();
			return t && Je(e, "clear", void 0, void 0, void 0), n;
		}
	}), [
		"keys",
		"values",
		"entries",
		Symbol.iterator
	].forEach((r) => {
		n[r] = _t(r, e, t);
	}), n;
}
function bt(e, t) {
	let n = yt(e, t);
	return (r, i, a) => i === "__v_isReactive" ? !e : i === "__v_isReadonly" ? e : i === "__v_isShallow" ? t : i === "__v_raw" ? r : Reflect.get(u(n, i) && i in r ? n : r, i, a);
}
var xt = { get: /* @__PURE__ */ bt(!1, !1) }, St = { get: /* @__PURE__ */ bt(!1, !0) }, Ct = { get: /* @__PURE__ */ bt(!0, !1) }, wt = /* @__PURE__ */ new WeakMap(), Tt = /* @__PURE__ */ new WeakMap(), Et = /* @__PURE__ */ new WeakMap(), Dt = /* @__PURE__ */ new WeakMap();
function Ot(e) {
	switch (e) {
		case "Object":
		case "Array": return 1;
		case "Map":
		case "Set":
		case "WeakMap":
		case "WeakSet": return 2;
		default: return 0;
	}
}
// @__NO_SIDE_EFFECTS__
function kt(e) {
	return /* @__PURE__ */ Pt(e) ? e : Mt(e, !1, ft, xt, wt);
}
// @__NO_SIDE_EFFECTS__
function At(e) {
	return Mt(e, !1, mt, St, Tt);
}
// @__NO_SIDE_EFFECTS__
function jt(e) {
	return Mt(e, !0, pt, Ct, Et);
}
function Mt(e, t, n, r, i) {
	if (!v(e) || e.__v_raw && !(t && e.__v_isReactive) || e.__v_skip || !Object.isExtensible(e)) return e;
	let a = i.get(e);
	if (a) return a;
	let o = Ot(S(e));
	if (o === 0) return e;
	let s = new Proxy(e, o === 2 ? r : n);
	return i.set(e, s), s;
}
// @__NO_SIDE_EFFECTS__
function Nt(e) {
	return /* @__PURE__ */ Pt(e) ? /* @__PURE__ */ Nt(e.__v_raw) : !!(e && e.__v_isReactive);
}
// @__NO_SIDE_EFFECTS__
function Pt(e) {
	return !!(e && e.__v_isReadonly);
}
// @__NO_SIDE_EFFECTS__
function I(e) {
	return !!(e && e.__v_isShallow);
}
// @__NO_SIDE_EFFECTS__
function Ft(e) {
	return e ? !!e.__v_raw : !1;
}
// @__NO_SIDE_EFFECTS__
function L(e) {
	let t = e && e.__v_raw;
	return t ? /* @__PURE__ */ L(t) : e;
}
function It(e) {
	return !u(e, "__v_skip") && Object.isExtensible(e) && k(e, "__v_skip", !0), e;
}
var R = (e) => v(e) ? /* @__PURE__ */ kt(e) : e, Lt = (e) => v(e) ? /* @__PURE__ */ jt(e) : e;
// @__NO_SIDE_EFFECTS__
function z(e) {
	return e ? e.__v_isRef === !0 : !1;
}
// @__NO_SIDE_EFFECTS__
function Rt(e) {
	return Bt(e, !1);
}
// @__NO_SIDE_EFFECTS__
function zt(e) {
	return Bt(e, !0);
}
function Bt(e, t) {
	return /* @__PURE__ */ z(e) ? e : new Vt(e, t);
}
var Vt = class {
	constructor(e, t) {
		this.dep = new He(), this.__v_isRef = !0, this.__v_isShallow = !1, this._rawValue = t ? e : /* @__PURE__ */ L(e), this._value = t ? e : R(e), this.__v_isShallow = t;
	}
	get value() {
		return this.dep.track(), this._value;
	}
	set value(e) {
		let t = this._rawValue, n = this.__v_isShallow || /* @__PURE__ */ I(e) || /* @__PURE__ */ Pt(e);
		e = n ? e : /* @__PURE__ */ L(e), O(e, t) && (this._rawValue = e, this._value = n ? e : R(e), this.dep.trigger());
	}
};
function Ht(e) {
	return /* @__PURE__ */ z(e) ? e.value : e;
}
var Ut = {
	get: (e, t, n) => t === "__v_raw" ? e : Ht(Reflect.get(e, t, n)),
	set: (e, t, n, r) => {
		let i = e[t];
		return /* @__PURE__ */ z(i) && !/* @__PURE__ */ z(n) ? (i.value = n, !0) : Reflect.set(e, t, n, r);
	}
};
function Wt(e) {
	return /* @__PURE__ */ Nt(e) ? e : new Proxy(e, Ut);
}
var Gt = class {
	constructor(e, t, n) {
		this.fn = e, this.setter = t, this._value = void 0, this.dep = new He(this), this.__v_isRef = !0, this.deps = void 0, this.depsTail = void 0, this.flags = 16, this.globalVersion = Be - 1, this.next = void 0, this.effect = this, this.__v_isReadonly = !t, this.isSSR = n;
	}
	notify() {
		if (this.flags |= 16, !(this.flags & 8) && j !== this) return ke(this, !0), !0;
	}
	get value() {
		let e = this.dep.track();
		return Fe(this), e && (e.version = this.dep.version), this._value;
	}
	set value(e) {
		this.setter && this.setter(e);
	}
};
// @__NO_SIDE_EFFECTS__
function Kt(e, t, n = !1) {
	let r, i;
	return h(e) ? r = e : (r = e.get, i = e.set), new Gt(r, i, n);
}
var qt = {}, Jt = /* @__PURE__ */ new WeakMap(), Yt = void 0;
function Xt(e, t = !1, n = Yt) {
	if (n) {
		let t = Jt.get(n);
		t || Jt.set(n, t = []), t.push(e);
	}
}
function Zt(e, n, i = t) {
	let { immediate: a, deep: o, once: s, scheduler: l, augmentJob: u, call: f } = i, p = (e) => o ? e : /* @__PURE__ */ I(e) || o === !1 || o === 0 ? Qt(e, 1) : Qt(e), m, g, _, v, y = !1, b = !1;
	if (/* @__PURE__ */ z(e) ? (g = () => e.value, y = /* @__PURE__ */ I(e)) : /* @__PURE__ */ Nt(e) ? (g = () => p(e), y = !0) : d(e) ? (b = !0, y = e.some((e) => /* @__PURE__ */ Nt(e) || /* @__PURE__ */ I(e)), g = () => e.map((e) => {
		if (/* @__PURE__ */ z(e)) return e.value;
		if (/* @__PURE__ */ Nt(e)) return p(e);
		if (h(e)) return f ? f(e, 2) : e();
	})) : g = h(e) ? n ? f ? () => f(e, 2) : e : () => {
		if (_) {
			N();
			try {
				_();
			} finally {
				P();
			}
		}
		let t = Yt;
		Yt = m;
		try {
			return f ? f(e, 3, [v]) : e(v);
		} finally {
			Yt = t;
		}
	} : r, n && o) {
		let e = g, t = o === !0 ? Infinity : o;
		g = () => Qt(e(), t);
	}
	let x = Ce(), S = () => {
		m.stop(), x && x.active && c(x.effects, m);
	};
	if (s && n) {
		let e = n;
		n = (...t) => {
			try {
				return e(...t);
			} finally {
				S();
			}
		};
	}
	let C = b ? Array(e.length).fill(qt) : qt, w = (e) => {
		if (m.flags & 1 && (m.dirty || e)) {
			if (n) {
				let t = m.run();
				if (e || o || y || (b ? t.some((e, t) => O(e, C[t])) : O(t, C))) {
					let e = Yt;
					N();
					try {
						_ && _(), Yt = m;
						let e = [
							t,
							C === qt ? void 0 : b && C[0] === qt ? [] : C,
							v
						];
						C = t, f ? f(n, 3, e) : n(...e);
					} finally {
						Yt = e, P();
					}
				}
			} else m.run();
		}
	};
	return u && u(w), m = new Te(g), m.scheduler = l ? () => l(w, !1) : w, v = (e) => Xt(e, !1, m), _ = m.onStop = () => {
		let e = Jt.get(m);
		if (e) {
			if (f) f(e, 4);
			else for (let t of e) t();
			Jt.delete(m);
		}
	}, n ? a ? w(!0) : C = m.run() : l ? l(w.bind(null, !0), !0) : m.run(), S.pause = m.pause.bind(m), S.resume = m.resume.bind(m), S.stop = S, S;
}
function Qt(e, t = Infinity, n) {
	if (t <= 0 || !v(e) || e.__v_skip || (n ||= /* @__PURE__ */ new Map(), (n.get(e) || 0) >= t)) return e;
	if (n.set(e, t), t--, /* @__PURE__ */ z(e)) Qt(e.value, t, n);
	else if (d(e)) for (let r = 0; r < e.length; r++) Qt(e[r], t, n);
	else if (p(e) || f(e)) e.forEach((e) => {
		Qt(e, t, n);
	});
	else if (C(e)) {
		for (let r in e) Qt(e[r], t, n);
		for (let r of Object.getOwnPropertySymbols(e)) Object.prototype.propertyIsEnumerable.call(e, r) && Qt(e[r], t, n);
	}
	return e;
}
// node_modules/.pnpm/@vue+runtime-core@3.5.44/node_modules/@vue/runtime-core/dist/runtime-core.esm-bundler.js
function $t(e, t, n, r) {
	try {
		return r ? e(...r) : e();
	} catch (e) {
		en(e, t, n);
	}
}
function B(e, t, n, r) {
	if (h(e)) {
		let i = $t(e, t, n, r);
		return i && y(i) && i.catch((e) => {
			en(e, t, n);
		}), i;
	}
	if (d(e)) {
		let i = [];
		for (let a = 0; a < e.length; a++) i.push(B(e[a], t, n, r));
		return i;
	}
}
function en(e, n, r, i = !0) {
	let a = n ? n.vnode : null, { errorHandler: o, throwUnhandledErrorInProduction: s } = n && n.appContext.config || t;
	if (n) {
		let t = n.parent, i = n.proxy, a = `https://vuejs.org/error-reference/#runtime-${r}`;
		for (; t;) {
			let n = t.ec;
			if (n) {
				for (let t = 0; t < n.length; t++) if (n[t](e, i, a) === !1) return;
			}
			t = t.parent;
		}
		if (o) {
			N(), $t(o, null, 10, [
				e,
				i,
				a
			]), P();
			return;
		}
	}
	tn(e, r, a, i, s);
}
function tn(e, t, n, r = !0, i = !1) {
	if (i) throw e;
	console.error(e);
}
var V = [], H = -1, U = [], nn = null, rn = 0, an = /* @__PURE__ */ Promise.resolve(), on = null;
function sn(e) {
	let t = on || an;
	return e ? t.then(this ? e.bind(this) : e) : t;
}
function cn(e) {
	let t = H + 1, n = V.length;
	for (; t < n;) {
		let r = t + n >>> 1, i = V[r], a = gn(i);
		a < e || a === e && i.flags & 2 ? t = r + 1 : n = r;
	}
	return t;
}
function ln(e) {
	if (!(e.flags & 1)) {
		let t = gn(e), n = V[V.length - 1];
		!n || !(e.flags & 2) && t >= gn(n) ? V.push(e) : V.splice(cn(t), 0, e), e.flags |= 1, un();
	}
}
function un() {
	on ||= an.then(_n);
}
function dn(e) {
	if (!d(e)) nn && e.id === -1 ? nn.splice(rn + 1, 0, e) : e.flags & 1 || (U.push(e), e.flags |= 1);
	else for (let t = 0; t < e.length; t++) U.push(e[t]);
	un();
}
function fn(e, t, n, r = H + 1) {
	for (; r < V.length; r++) {
		let n = V[r];
		if (n && n.flags & 2) {
			if (e && n.id !== e.uid || t !== void 0 && !(n.id >= t)) continue;
			V.splice(r, 1), r = e ? r - 1 : H, n.flags & 4 && (n.flags &= -2), n(), n.flags & 4 || (n.flags &= -2);
		}
	}
}
function pn(e, t, n = 0) {
	if (U.length) {
		let e = U, r = 0;
		if (t) {
			e = [];
			for (let i = 0; i < U.length; i++) {
				let a = U[i];
				(a.i === void 0 ? t.has(a) : !a.i || a.i.uid < n) ? U[r++] = a : e.push(a);
			}
		}
		let i = [...new Set(e)].sort((e, t) => gn(e) - gn(t));
		if (U.length = r, nn) {
			for (let e = 0; e < i.length; e++) nn.push(i[e]);
			return;
		}
		for (nn = i, rn = 0; rn < nn.length; rn++) {
			let e = nn[rn];
			e.flags & 4 && (e.flags &= -2), e.flags & 8 || e(), e.flags &= -2;
		}
		nn = null, rn = 0;
	}
}
var mn = !1;
function hn(e, t) {
	if (mn) {
		e();
		return;
	}
	let n = t === void 0 ? void 0 : new Set(U);
	e(), mn = !0;
	try {
		fn(void 0, t), pn(void 0, n, t);
	} finally {
		mn = !1;
	}
}
var gn = (e) => e.id == null ? e.flags & 2 ? -1 : Infinity : e.id;
function _n(e) {
	try {
		for (H = 0; H < V.length; H++) {
			let e = V[H];
			e && !(e.flags & 8) && (e.flags & 4 && (e.flags &= -2), $t(e, e.i, e.i ? 15 : 14), e.flags & 4 || (e.flags &= -2));
		}
	} finally {
		for (; H < V.length; H++) {
			let e = V[H];
			e && (e.flags &= -2);
		}
		H = -1, V.length = 0, pn(e), on = null, (V.length || U.length) && _n(e);
	}
}
var W, vn = [], yn = !1;
function bn(e, ...t) {
	W ? W.emit(e, ...t) : yn || vn.push({
		event: e,
		args: t
	});
}
function xn(e, t) {
	W = e, W ? (W.enabled = !0, vn.forEach(({ event: e, args: t }) => W.emit(e, ...t)), vn = []) : typeof window < "u" && window.HTMLElement && !(window.navigator?.userAgent)?.includes("jsdom") ? ((t.__VUE_DEVTOOLS_HOOK_REPLAY__ = t.__VUE_DEVTOOLS_HOOK_REPLAY__ || []).push((e) => {
		xn(e, t);
	}), setTimeout(() => {
		W || (t.__VUE_DEVTOOLS_HOOK_REPLAY__ = null, yn = !0, vn = []);
	}, 3e3)) : (yn = !0, vn = []);
}
function Sn(e, t) {
	bn("app:init", e, t, {
		Fragment: J,
		Text: Pi,
		Comment: Y,
		Static: Fi
	});
}
function Cn(e) {
	bn("app:unmount", e);
}
var wn = /* @__PURE__ */ On("component:added"), Tn = /* @__PURE__ */ On("component:updated"), En = /* @__PURE__ */ On("component:removed"), Dn = (e) => {
	W && typeof W.cleanupBuffer == "function" && !W.cleanupBuffer(e) && En(e);
};
// @__NO_SIDE_EFFECTS__
function On(e) {
	return (t) => {
		bn(e, t.appContext.app, t.uid, t.parent ? t.parent.uid : void 0, t);
	};
}
function kn(e, t, n) {
	bn("component:emit", e.appContext.app, e, t, n);
}
var G = null, An = null;
function jn(e) {
	let t = G;
	return G = e, An = e && e.type.__scopeId || null, t;
}
function Mn(e, t = G, n) {
	if (!t || e._n) return e;
	let r = (...n) => {
		r._d && Bi(-1);
		let i = jn(t), a = Ii.length, o;
		try {
			o = e(...n);
		} finally {
			for (let e = Ii.length; e > a; e--) Ri();
			jn(i), r._d && Bi(1);
		}
		return __VUE_PROD_DEVTOOLS__ && Tn(t), o;
	};
	return r._n = !0, r._c = !0, r._d = !0, r;
}
function Nn(e, t, n, r) {
	let i = e.dirs, a = t && t.dirs;
	for (let o = 0; o < i.length; o++) {
		let s = i[o];
		a && (s.oldValue = a[o].value);
		let c = s.dir[r];
		c && (N(), B(c, n, 8, [
			e.el,
			s,
			e,
			t
		]), P());
	}
}
function Pn(e, t) {
	if (Z) {
		let n = Z.provides, r = Z.parent && Z.parent.provides;
		r === n && (n = Z.provides = Object.create(r)), n[e] = t;
	}
}
function Fn(e, t, n = !1) {
	let r = aa();
	if (r || Ur) {
		let i = Ur ? Ur._context.provides : r ? r.parent == null || r.ce ? r.vnode.appContext && r.vnode.appContext.provides : r.parent.provides : void 0;
		if (i && e in i) return i[e];
		if (arguments.length > 1) return n && h(t) ? t.call(r && r.proxy) : t;
	}
}
var In = /* @__PURE__ */ Symbol.for("v-scx"), Ln = () => Fn(In);
function Rn(e, t) {
	return Bn(e, null, t);
}
function zn(e, t, n) {
	return Bn(e, t, n);
}
function Bn(e, n, i = t) {
	let { immediate: a, deep: o, flush: c, once: l } = i, u = s({}, i), d = n && a || !n && c !== "post", f;
	if (da) {
		if (c === "sync") {
			let e = Ln();
			f = e.__watcherHandles ||= [];
		} else if (!d) {
			let e = () => {};
			return e.stop = r, e.resume = r, e.pause = r, e;
		}
	}
	let p = Z;
	u.call = (e, t, n) => B(e, p, t, n);
	let m = !1;
	c === "post" ? u.scheduler = (e) => {
		e.i = p, q(e, p && p.suspense);
	} : c !== "sync" && (m = !0, u.scheduler = (e, t) => {
		t ? e() : ln(e);
	}), u.augmentJob = (e) => {
		n && (e.flags |= 4), m && (e.flags |= 2, p && (e.id = p.uid, e.i = p));
	};
	let h = Zt(e, n, u);
	return da && (f ? f.push(h) : d && h()), h;
}
function Vn(e, t, n) {
	let r = this.proxy, i = g(e) ? e.includes(".") ? Hn(r, e) : () => r[e] : e.bind(r, r), a;
	h(t) ? a = t : (a = t.handler, n = t);
	let o = ca(this), s = Bn(i, a.bind(r), n);
	return o(), s;
}
function Hn(e, t) {
	let n = t.split(".");
	return () => {
		let t = e;
		for (let e = 0; e < n.length && t; e++) t = t[n[e]];
		return t;
	};
}
var Un = /* @__PURE__ */ Symbol("_vte"), Wn = (e) => e.__isTeleport, Gn = /* @__PURE__ */ Symbol("_leaveCb");
function Kn(e) {
	let t = e[0];
	if (e.length > 1) {
		for (let n of e) if (n.type !== Y) {
			t = n;
			break;
		}
	}
	return t;
}
function qn(e) {
	if (!rr(e)) return Wn(e.type) && e.children ? Kn(e.children) : e;
	if (e.component) return e.component.subTree;
	let { shapeFlag: t, children: n } = e;
	if (n) {
		if (t & 16) return n[0];
		if (t & 32 && h(n.default)) return n.default();
	}
}
function Jn(e, t) {
	if (e.shapeFlag & 6 && e.component) {
		e.transition = t;
		let n = e.component.subTree;
		Jn(Wn(n.type) && qn(n) || n, t);
	} else if (e.shapeFlag & 128) e.ssContent.transition = t.clone(e.ssContent), e.ssFallback.transition = t.clone(e.ssFallback);
	else if (e.transition = t, e.type === J && d(e.children)) {
		let n = Xr(e.children, !1);
		n && Jn(n, t);
	}
}
function Yn(e) {
	if (e.component) return Yn(e.component.subTree);
	if (e.type === J) {
		let t = d(e.children) && Xr(e.children, !1);
		return t ? Yn(t) : void 0;
	}
	if (e.type !== Y) return e;
}
// @__NO_SIDE_EFFECTS__
function Xn(e, t) {
	return h(e) ? /* @__PURE__ */ s({ name: e.name }, t, { setup: e }) : e;
}
function Zn(e) {
	e.ids = [
		e.ids[0] + e.ids[2]++ + "-",
		0,
		0
	];
}
function Qn(e, t) {
	let n;
	return !!((n = Object.getOwnPropertyDescriptor(e, t)) && !n.configurable);
}
var $n = /* @__PURE__ */ new WeakMap();
function er(e, n, r, a, o = !1) {
	if (d(e)) {
		e.forEach((e, t) => er(e, n && (d(n) ? n[t] : n), r, a, o));
		return;
	}
	if (nr(a) && !o) {
		a.shapeFlag & 512 && a.type.__asyncResolved && a.component.subTree.component && er(e, n, r, a.component.subTree);
		return;
	}
	let s = a.shapeFlag & 4 ? va(a.component) : a.el, l = o ? null : s, { i: f, r: p } = e, m = n && n.r, _ = f.refs === t ? f.refs = {} : f.refs, v = f.setupState, y = /* @__PURE__ */ L(v), b = v === t ? i : (e) => !Qn(_, e) && u(y, e), x = (e, t) => !(t && Qn(_, t));
	if (m != null && m !== p) {
		if (tr(n), g(m)) _[m] = null, b(m) && (v[m] = null);
		else if (/* @__PURE__ */ z(m)) {
			let e = n;
			x(m, e.k) && (m.value = null), e.k && (_[e.k] = null);
		}
	}
	if (h(p)) $t(p, f, 12, [l, _]);
	else {
		let t = g(p), n = /* @__PURE__ */ z(p);
		if (t || n) {
			let i = () => {
				if (e.f) {
					let n = t ? b(p) ? v[p] : _[p] : x(p) || !e.k ? p.value : _[e.k];
					if (o) d(n) && c(n, s);
					else if (d(n)) n.includes(s) || n.push(s);
					else if (t) _[p] = [s], b(p) && (v[p] = _[p]);
					else {
						let t = [s];
						x(p, e.k) && (p.value = t), e.k && (_[e.k] = t);
					}
				} else t ? (_[p] = l, b(p) && (v[p] = l)) : n && (x(p, e.k) && (p.value = l), e.k && (_[e.k] = l));
			};
			if (l) {
				let t = () => {
					i(), $n.delete(e);
				};
				t.id = -1, $n.set(e, t), q(t, r);
			} else tr(e), i();
		}
	}
}
function tr(e) {
	let t = $n.get(e);
	t && (t.flags |= 8, $n.delete(e));
}
ce().requestIdleCallback, ce().cancelIdleCallback;
var nr = (e) => !!e.type.__asyncLoader, rr = (e) => e.type.__isKeepAlive;
function ir(e, t) {
	or(e, "a", t);
}
function ar(e, t) {
	or(e, "da", t);
}
function or(e, t, n = Z) {
	let r = e.__wdc ||= () => {
		let t = n;
		for (; t;) {
			if (t.isDeactivated) return;
			t = t.parent;
		}
		return e();
	};
	if (cr(t, r, n), n) {
		let e = n.parent;
		for (; e && e.parent;) rr(e.parent.vnode) && sr(r, t, n, e), e = e.parent;
	}
}
function sr(e, t, n, r) {
	let i = cr(t, e, r, !0);
	hr(() => {
		c(r[t], i);
	}, n);
}
function cr(e, t, n = Z, r = !1) {
	if (n) {
		let i = n[e] || (n[e] = []), a = t.__weh ||= (...r) => {
			N();
			let i = ca(n), a = B(t, n, e, r);
			return i(), P(), a;
		};
		return r ? i.unshift(a) : i.push(a), a;
	}
}
var lr = (e) => (t, n = Z) => {
	da && e !== "sp" || cr(e, (...e) => t(...e), n);
}, ur = lr("bm"), dr = lr("m"), fr = lr("bu"), pr = lr("u"), mr = lr("bum"), hr = lr("um"), gr = lr("sp"), _r = lr("rtg"), vr = lr("rtc");
function yr(e, t = Z) {
	cr("ec", e, t);
}
var br = /* @__PURE__ */ Symbol.for("v-ndc"), xr = (e) => e ? ua(e) ? va(e) : xr(e.parent) : null, Sr = /* @__PURE__ */ s(/* @__PURE__ */ Object.create(null), {
	$: (e) => e,
	$el: (e) => e.vnode.el,
	$data: (e) => e.data,
	$props: (e) => e.props,
	$attrs: (e) => e.attrs,
	$slots: (e) => e.slots,
	$refs: (e) => e.refs,
	$parent: (e) => xr(e.parent),
	$root: (e) => xr(e.root),
	$host: (e) => e.ce,
	$emit: (e) => e.emit,
	$options: (e) => __VUE_OPTIONS_API__ ? jr(e) : e.type,
	$forceUpdate: (e) => e.f ||= () => {
		ln(e.update);
	},
	$nextTick: (e) => e.n ||= sn.bind(e.proxy),
	$watch: (e) => __VUE_OPTIONS_API__ ? Vn.bind(e) : r
}), Cr = (e, n) => e !== t && !e.__isScriptSetup && u(e, n), wr = {
	get({ _: e }, n) {
		if (n === "__v_skip") return !0;
		let { ctx: r, setupState: i, data: a, props: o, accessCache: s, type: c, appContext: l } = e;
		if (n[0] !== "$") {
			let e = s[n];
			if (e !== void 0) switch (e) {
				case 1: return i[n];
				case 2: return a[n];
				case 4: return r[n];
				case 3: return o[n];
			}
			else if (Cr(i, n)) return s[n] = 1, i[n];
			else if (__VUE_OPTIONS_API__ && a !== t && u(a, n)) return s[n] = 2, a[n];
			else if (u(o, n)) return s[n] = 3, o[n];
			else if (r !== t && u(r, n)) return s[n] = 4, r[n];
			else (!__VUE_OPTIONS_API__ || Er) && (s[n] = 0);
		}
		let d = Sr[n], f, p;
		if (d) return n === "$attrs" && F(e.attrs, "get", ""), d(e);
		if ((f = c.__cssModules) && (f = f[n])) return f;
		if (r !== t && u(r, n)) return s[n] = 4, r[n];
		if (p = l.config.globalProperties, u(p, n)) return p[n];
	},
	set({ _: e }, n, r) {
		let { data: i, setupState: a, ctx: o } = e;
		return Cr(a, n) ? (a[n] = r, !0) : __VUE_OPTIONS_API__ && i !== t && u(i, n) ? (i[n] = r, !0) : u(e.props, n) || n[0] === "$" && n.slice(1) in e ? !1 : (o[n] = r, !0);
	},
	has({ _: { data: e, setupState: n, accessCache: r, ctx: i, appContext: a, props: o, type: s } }, c) {
		let l;
		return !!(r[c] || __VUE_OPTIONS_API__ && e !== t && c[0] !== "$" && u(e, c) || Cr(n, c) || u(o, c) || u(i, c) || u(Sr, c) || u(a.config.globalProperties, c) || (l = s.__cssModules) && l[c]);
	},
	defineProperty(e, t, n) {
		return n.get == null ? u(n, "value") && this.set(e, t, n.value, null) : e._.accessCache[t] = 0, Reflect.defineProperty(e, t, n);
	}
};
function Tr(e) {
	return d(e) ? e.reduce((e, t) => (e[t] = null, e), {}) : e;
}
var Er = !0;
function Dr(e) {
	let t = jr(e), n = e.proxy, i = e.ctx;
	Er = !1, t.beforeCreate && kr(t.beforeCreate, e, "bc");
	let { data: a, computed: o, methods: s, watch: c, provide: l, inject: u, created: f, beforeMount: p, mounted: m, beforeUpdate: g, updated: _, activated: y, deactivated: b, beforeDestroy: x, beforeUnmount: S, destroyed: C, unmounted: w, render: T, renderTracked: ee, renderTriggered: te, errorCaptured: E, serverPrefetch: ne, expose: D, inheritAttrs: re, components: ie, directives: O, filters: ae } = t;
	if (u && Or(u, i, null), s) for (let e in s) {
		let t = s[e];
		h(t) && (i[e] = t.bind(n));
	}
	if (a) {
		let t = a.call(n, n);
		v(t) && (e.data = /* @__PURE__ */ kt(t));
	}
	if (Er = !0, o) for (let e in o) {
		let t = o[e], a = ba({
			get: h(t) ? t.bind(n, n) : h(t.get) ? t.get.bind(n, n) : r,
			set: !h(t) && h(t.set) ? t.set.bind(n) : r
		});
		Object.defineProperty(i, e, {
			enumerable: !0,
			configurable: !0,
			get: () => a.value,
			set: (e) => a.value = e
		});
	}
	if (c) for (let e in c) Ar(c[e], i, n, e);
	if (l) {
		let e = h(l) ? l.call(n) : l;
		Reflect.ownKeys(e).forEach((t) => {
			Pn(t, e[t]);
		});
	}
	f && kr(f, e, "c");
	function k(e, t) {
		d(t) ? t.forEach((t) => e(t.bind(n))) : t && e(t.bind(n));
	}
	if (k(ur, p), k(dr, m), k(fr, g), k(pr, _), k(ir, y), k(ar, b), k(yr, E), k(vr, ee), k(_r, te), k(mr, S), k(hr, w), k(gr, ne), d(D)) {
		if (D.length) {
			let t = e.exposed ||= {};
			D.forEach((e) => {
				Object.defineProperty(t, e, {
					get: () => n[e],
					set: (t) => n[e] = t,
					enumerable: !0
				});
			});
		} else e.exposed ||= {};
	}
	T && e.render === r && (e.render = T), re != null && (e.inheritAttrs = re), ie && (e.components = ie), O && (e.directives = O), ne && Zn(e);
}
function Or(e, t, n = r) {
	d(e) && (e = Ir(e));
	for (let n in e) {
		let r = e[n], i;
		i = v(r) ? "default" in r ? Fn(r.from || n, r.default, !0) : Fn(r.from || n) : Fn(r), /* @__PURE__ */ z(i) ? Object.defineProperty(t, n, {
			enumerable: !0,
			configurable: !0,
			get: () => i.value,
			set: (e) => i.value = e
		}) : t[n] = i;
	}
}
function kr(e, t, n) {
	B(d(e) ? e.map((e) => e.bind(t.proxy)) : e.bind(t.proxy), t, n);
}
function Ar(e, t, n, r) {
	let i = r.includes(".") ? Hn(n, r) : () => n[r];
	if (g(e)) {
		let n = t[e];
		h(n) && zn(i, n);
	} else if (h(e)) zn(i, e.bind(n));
	else if (v(e)) {
		if (d(e)) e.forEach((e) => Ar(e, t, n, r));
		else {
			let r = h(e.handler) ? e.handler.bind(n) : t[e.handler];
			h(r) && zn(i, r, e);
		}
	}
}
function jr(e) {
	let t = e.type, { mixins: n, extends: r } = t, { mixins: i, optionsCache: a, config: { optionMergeStrategies: o } } = e.appContext, s = a.get(t), c;
	return s ? c = s : !i.length && !n && !r ? c = t : (c = {}, i.length && i.forEach((e) => Mr(c, e, o, !0)), Mr(c, t, o)), v(t) && a.set(t, c), c;
}
function Mr(e, t, n, r = !1) {
	let { mixins: i, extends: a } = t;
	a && Mr(e, a, n, !0), i && i.forEach((t) => Mr(e, t, n, !0));
	for (let i in t) if (!(r && i === "expose")) {
		let r = Nr[i] || n && n[i];
		e[i] = r ? r(e[i], t[i]) : t[i];
	}
	return e;
}
var Nr = {
	data: Pr,
	props: Rr,
	emits: Rr,
	methods: Lr,
	computed: Lr,
	beforeCreate: K,
	created: K,
	beforeMount: K,
	mounted: K,
	beforeUpdate: K,
	updated: K,
	beforeDestroy: K,
	beforeUnmount: K,
	destroyed: K,
	unmounted: K,
	activated: K,
	deactivated: K,
	errorCaptured: K,
	serverPrefetch: K,
	components: Lr,
	directives: Lr,
	watch: zr,
	provide: Pr,
	inject: Fr
};
function Pr(e, t) {
	return t ? e ? function() {
		return s(h(e) ? e.call(this, this) : e, h(t) ? t.call(this, this) : t);
	} : t : e;
}
function Fr(e, t) {
	return Lr(Ir(e), Ir(t));
}
function Ir(e) {
	if (d(e)) {
		let t = {};
		for (let n = 0; n < e.length; n++) t[e[n]] = e[n];
		return t;
	}
	return e;
}
function K(e, t) {
	return e ? [...new Set([].concat(e, t))] : t;
}
function Lr(e, t) {
	return e ? s(/* @__PURE__ */ Object.create(null), e, t) : t;
}
function Rr(e, t) {
	return e ? d(e) && d(t) ? [.../* @__PURE__ */ new Set([...e, ...t])] : s(/* @__PURE__ */ Object.create(null), Tr(e), Tr(t ?? {})) : t;
}
function zr(e, t) {
	if (!e) return t;
	if (!t) return e;
	let n = s(/* @__PURE__ */ Object.create(null), e);
	for (let r in t) n[r] = K(e[r], t[r]);
	return n;
}
function Br() {
	return {
		app: null,
		config: {
			isNativeTag: i,
			performance: !1,
			globalProperties: {},
			optionMergeStrategies: {},
			errorHandler: void 0,
			warnHandler: void 0,
			compilerOptions: {}
		},
		mixins: [],
		components: {},
		directives: {},
		provides: /* @__PURE__ */ Object.create(null),
		optionsCache: /* @__PURE__ */ new WeakMap(),
		propsCache: /* @__PURE__ */ new WeakMap(),
		emitsCache: /* @__PURE__ */ new WeakMap()
	};
}
var Vr = 0;
function Hr(e, t) {
	return function(n, r = null) {
		h(n) || (n = s({}, n)), r != null && !v(r) && (r = null);
		let i = Br(), a = /* @__PURE__ */ new WeakSet(), o = [], c = !1, l = i.app = {
			_uid: Vr++,
			_component: n,
			_props: r,
			_container: null,
			_context: i,
			_instance: null,
			version: Sa,
			get config() {
				return i.config;
			},
			set config(e) {},
			use(e, ...t) {
				return a.has(e) || (e && h(e.install) ? (a.add(e), e.install(l, ...t)) : h(e) && (a.add(e), e(l, ...t))), l;
			},
			mixin(e) {
				return __VUE_OPTIONS_API__ && (i.mixins.includes(e) || i.mixins.push(e)), l;
			},
			component(e, t) {
				return t ? (i.components[e] = t, l) : i.components[e];
			},
			directive(e, t) {
				return t ? (i.directives[e] = t, l) : i.directives[e];
			},
			mount(a, o, s) {
				if (!c) {
					let u = l._ceVNode || X(n, r);
					return u.appContext = i, s === !0 ? s = "svg" : s === !1 && (s = void 0), o && t ? t(u, a) : e(u, a, s), c = !0, l._container = a, a.__vue_app__ = l, __VUE_PROD_DEVTOOLS__ && (l._instance = u.component, Sn(l, Sa)), va(u.component);
				}
			},
			onUnmount(e) {
				o.push(e);
			},
			unmount() {
				c && (B(o, l._instance, 16), e(null, l._container), __VUE_PROD_DEVTOOLS__ && (l._instance = null, Cn(l)), delete l._container.__vue_app__);
			},
			provide(e, t) {
				return i.provides[e] = t, l;
			},
			runWithContext(e) {
				let t = Ur;
				Ur = l;
				try {
					return e();
				} finally {
					Ur = t;
				}
			}
		};
		return l;
	};
}
var Ur = null, Wr = (e, t) => t === "modelValue" || t === "model-value" ? e.modelModifiers : e[`${t}Modifiers`] || e[`${E(t)}Modifiers`] || e[`${D(t)}Modifiers`];
function Gr(e, n, ...r) {
	if (e.isUnmounted) return;
	let i = e.vnode.props || t, a = r, o = n.startsWith("update:"), s = o && Wr(i, n.slice(7));
	s && (s.trim && (a = r.map((e) => g(e) ? e.trim() : e)), s.number && (a = a.map(oe))), __VUE_PROD_DEVTOOLS__ && kn(e, n, a);
	let c, l = i[c = ie(n)] || i[c = ie(E(n))];
	!l && o && (l = i[c = ie(D(n))]), l && B(l, e, 6, a);
	let u = i[c + "Once"];
	if (u) {
		if (!e.emitted) e.emitted = {};
		else if (e.emitted[c]) return;
		e.emitted[c] = !0, B(u, e, 6, a);
	}
}
var Kr = /* @__PURE__ */ new WeakMap();
function qr(e, t, n = !1) {
	let r = __VUE_OPTIONS_API__ && n ? Kr : t.emitsCache, i = r.get(e);
	if (i !== void 0) return i;
	let a = e.emits, o = {}, c = !1;
	if (__VUE_OPTIONS_API__ && !h(e)) {
		let r = (e) => {
			let n = qr(e, t, !0);
			n && (c = !0, s(o, n));
		};
		!n && t.mixins.length && t.mixins.forEach(r), e.extends && r(e.extends), e.mixins && e.mixins.forEach(r);
	}
	return !a && !c ? (v(e) && r.set(e, null), null) : (d(a) ? a.forEach((e) => o[e] = null) : s(o, a), v(e) && r.set(e, o), o);
}
function Jr(e, t) {
	return !e || !a(t) ? !1 : (t = t.slice(2), t = t === "Once" ? t : t.replace(/Once$/, ""), u(e, t[0].toLowerCase() + t.slice(1)) || u(e, D(t)) || u(e, t));
}
function Yr(e) {
	let { type: t, vnode: n, proxy: r, withProxy: i, propsOptions: [a], slots: s, attrs: c, emit: l, render: u, renderCache: d, props: f, data: p, setupState: m, ctx: h, inheritAttrs: g } = e, _ = jn(e), v, y;
	try {
		if (n.shapeFlag & 4) {
			let e = i || r, t = e;
			v = Xi(u.call(t, e, d, f, m, p, h)), y = c;
		} else {
			let e = t;
			v = Xi(e.length > 1 ? e(f, {
				attrs: c,
				slots: s,
				emit: l
			}) : e(f, null)), y = t.props ? c : Zr(c);
		}
	} catch (t) {
		Ii.length = 0, en(t, e, 1), v = X(Y);
	}
	let b = v;
	if (y && g !== !1) {
		let e = Object.keys(y), { shapeFlag: t } = b;
		e.length && t & 7 && (a && e.some(o) && (y = Qr(y, a)), b = Ji(b, y, !1, !0));
	}
	return n.dirs && (b = Ji(b, null, !1, !0), b.dirs = b.dirs ? b.dirs.concat(n.dirs) : n.dirs), n.transition && Jn(Wn(b.type) && qn(b) || b, n.transition), v = b, jn(_), v;
}
function Xr(e, t = !0) {
	let n;
	for (let t = 0; t < e.length; t++) {
		let r = e[t];
		if (Vi(r)) {
			if (r.type !== Y || r.children === "v-if") {
				if (n) return;
				n = r;
			}
		} else return;
	}
	return n;
}
var Zr = (e) => {
	let t;
	for (let n in e) (n === "class" || n === "style" || a(n)) && ((t ||= {})[n] = e[n]);
	return t;
}, Qr = (e, t) => {
	let n = {};
	for (let r in e) o(r) && r.slice(9) in t || (n[r] = e[r]);
	return n;
};
function $r(e, t, n) {
	let { props: r, children: i, component: a } = e, { props: o, children: s, patchFlag: c } = t, l = a.emitsOptions;
	if (t.dirs || t.transition) return !0;
	if (n && c >= 0) {
		if (c & 1024) return !0;
		if (c & 16) return r ? ei(r, o, l) : !!o;
		if (c & 8) {
			let e = t.dynamicProps;
			for (let t = 0; t < e.length; t++) {
				let n = e[t];
				if (ti(o, r, n) && !Jr(l, n)) return !0;
			}
		}
	} else return (i || s) && (!s || !s.$stable) ? !0 : r === o ? !1 : r ? !o || ei(r, o, l) : !!o;
	return !1;
}
function ei(e, t, n) {
	let r = Object.keys(t);
	if (r.length !== Object.keys(e).length) return !0;
	for (let i = 0; i < r.length; i++) {
		let a = r[i];
		if (!u(e, a) || ti(t, e, a) && !Jr(n, a)) return !0;
	}
	return !1;
}
function ti(e, t, n) {
	let r = e[n], i = t[n];
	return n === "style" && v(r) && v(i) ? !xe(r, i) : r !== i;
}
function ni({ vnode: e, parent: t, suspense: n }, r) {
	for (; t;) {
		let n = t.subTree;
		if (n.suspense && n.suspense.activeBranch === e && (n.suspense.vnode.el = n.el = r, e = n), n === e) (e = t.vnode).el = r, t = t.parent;
		else break;
	}
	n && n.activeBranch === e && (n.vnode.el = r);
}
var ri = {}, ii = () => Object.create(ri), ai = (e) => Object.getPrototypeOf(e) === ri;
function oi(e, t, n, r = !1) {
	let i = {}, a = ii();
	e.propsDefaults = /* @__PURE__ */ Object.create(null), ci(e, t, i, a);
	for (let t in e.propsOptions[0]) t in i || (i[t] = void 0);
	e.props = n ? r ? i : /* @__PURE__ */ At(i) : e.type.props ? i : a, e.attrs = a;
}
function si(e, t, n, r) {
	let { props: i, attrs: a, vnode: { patchFlag: o } } = e, s = /* @__PURE__ */ L(i), [c] = e.propsOptions, l = !1;
	if ((r || o > 0) && !(o & 16)) {
		if (o & 8) {
			let n = e.vnode.dynamicProps;
			for (let r = 0; r < n.length; r++) {
				let o = n[r];
				if (Jr(e.emitsOptions, o)) continue;
				let d = t[o];
				if (c) {
					if (u(a, o)) d !== a[o] && (a[o] = d, l = !0);
					else {
						let t = E(o);
						i[t] = li(c, s, t, d, e, !1);
					}
				} else d !== a[o] && (a[o] = d, l = !0);
			}
		}
	} else {
		ci(e, t, i, a) && (l = !0);
		let r;
		for (let a in s) t && (u(t, a) || (r = D(a)) !== a && u(t, r)) || (c ? n && (n[a] !== void 0 || n[r] !== void 0) && (i[a] = li(c, s, a, void 0, e, !0)) : delete i[a]);
		if (a !== s) for (let e in a) t && u(t, e) || (delete a[e], l = !0);
	}
	l && Je(e.attrs, "set", "");
}
function ci(e, n, r, i) {
	let [a, o] = e.propsOptions, s = !1, c;
	if (n) for (let t in n) {
		if (T(t)) continue;
		let l = n[t], d;
		a && u(a, d = E(t)) ? !o || !o.includes(d) ? r[d] = l : (c ||= {})[d] = l : Jr(e.emitsOptions, t) || t in i && l === i[t] || (i[t] = l, s = !0);
	}
	if (o) {
		let n = /* @__PURE__ */ L(r), i = c || t;
		for (let t = 0; t < o.length; t++) {
			let s = o[t];
			r[s] = li(a, n, s, i[s], e, !u(i, s));
		}
	}
	return s;
}
function li(e, t, n, r, i, a) {
	let o = e[n];
	if (o != null) {
		let e = u(o, "default");
		if (e && r === void 0) {
			let e = o.default;
			if (o.type !== Function && !o.skipFactory && h(e)) {
				let { propsDefaults: a } = i;
				if (n in a) r = a[n];
				else {
					let o = ca(i);
					r = a[n] = e.call(null, t), o();
				}
			} else r = e;
			i.ce && i.ce._setProp(n, r);
		}
		o[0] && (a && !e ? r = !1 : o[1] && (r === "" || r === D(n)) && (r = !0));
	}
	return r;
}
var ui = /* @__PURE__ */ new WeakMap();
function di(e, r, i = !1) {
	let a = __VUE_OPTIONS_API__ && i ? ui : r.propsCache, o = a.get(e);
	if (o) return o;
	let c = e.props, l = {}, f = [], p = !1;
	if (__VUE_OPTIONS_API__ && !h(e)) {
		let t = (e) => {
			p = !0;
			let [t, n] = di(e, r, !0);
			s(l, t), n && f.push(...n);
		};
		!i && r.mixins.length && r.mixins.forEach(t), e.extends && t(e.extends), e.mixins && e.mixins.forEach(t);
	}
	if (!c && !p) return v(e) && a.set(e, n), n;
	if (d(c)) for (let e = 0; e < c.length; e++) {
		let n = E(c[e]);
		fi(n) && (l[n] = t);
	}
	else if (c) for (let e in c) {
		let t = E(e);
		if (fi(t)) {
			let n = c[e], r = l[t] = d(n) || h(n) ? { type: n } : s({}, n), i = r.type, a = !1, o = !0;
			if (d(i)) for (let e = 0; e < i.length; ++e) {
				let t = i[e], n = h(t) && t.name;
				if (n === "Boolean") {
					a = !0;
					break;
				}
				n === "String" && (o = !1);
			}
			else a = h(i) && i.name === "Boolean";
			r[0] = a, r[1] = o, (a || u(r, "default")) && f.push(t);
		}
	}
	let m = [l, f];
	return v(e) && a.set(e, m), m;
}
function fi(e) {
	return e[0] !== "$" && !T(e);
}
var pi = (e) => e === "_" || e === "_ctx" || e === "$stable", mi = (e) => d(e) ? e.map(Xi) : [Xi(e)], hi = (e, t, n) => {
	if (t._n) return t;
	let r = Mn((...e) => mi(t(...e)), n);
	return r._c = !1, r;
}, gi = (e, t, n) => {
	let r = e._ctx;
	for (let n in e) {
		if (pi(n)) continue;
		let i = e[n];
		if (h(i)) t[n] = hi(n, i, r);
		else if (i != null) {
			let e = mi(i);
			t[n] = () => e;
		}
	}
}, _i = (e, t) => {
	let n = mi(t);
	e.slots.default = () => n;
}, vi = (e, t, n) => {
	for (let r in t) (n || !pi(r)) && (e[r] = t[r]);
}, yi = (e, t, n) => {
	let r = e.slots = ii();
	if (e.vnode.shapeFlag & 32) {
		let e = t._;
		e ? (vi(r, t, n), n && k(r, "_", e, !0)) : gi(t, r);
	} else t && _i(e, t);
}, bi = (e, n, r) => {
	let { vnode: i, slots: a } = e, o = !0, s = t;
	if (i.shapeFlag & 32) {
		let e = n._;
		e ? r && e === 1 ? o = !1 : vi(a, n, r) : (o = !n.$stable, gi(n, a)), s = n;
	} else n && (_i(e, n), s = { default: 1 });
	if (o) for (let e in a) !pi(e) && s[e] == null && delete a[e];
};
function xi() {
	typeof __VUE_OPTIONS_API__ != "boolean" && (ce().__VUE_OPTIONS_API__ = !0), typeof __VUE_PROD_DEVTOOLS__ != "boolean" && (ce().__VUE_PROD_DEVTOOLS__ = !1), typeof __VUE_PROD_HYDRATION_MISMATCH_DETAILS__ != "boolean" && (ce().__VUE_PROD_HYDRATION_MISMATCH_DETAILS__ = !1);
}
var q = Ni;
function Si(e) {
	return Ci(e);
}
function Ci(e, i) {
	xi();
	let a = ce();
	a.__VUE__ = !0, __VUE_PROD_DEVTOOLS__ && xn(a.__VUE_DEVTOOLS_GLOBAL_HOOK__, a);
	let { insert: o, remove: s, patchProp: c, createElement: l, createText: u, createComment: d, setText: f, setElementText: p, parentNode: m, nextSibling: h, setScopeId: g = r, insertStaticContent: _ } = e, v = (e, t, r, i = null, a = null, o = null, s = void 0, c = null, l = !!t.dynamicChildren) => {
		if (e === t) return;
		e && !Hi(e, t) && (i = be(e), he(e, a, o, !0), e = null), t.patchFlag === -2 && (l = !1, t.dynamicChildren = null), t.dynamicChildren && e && e.dynamicChildren && e.dynamicChildren.hasOnce && (t.dynamicChildren === n && (t.dynamicChildren = []), t.dynamicChildren.hasOnce = !0);
		let { type: u, ref: d, shapeFlag: f } = t;
		switch (u) {
			case Pi:
				y(e, t, r, i);
				break;
			case Y:
				b(e, t, r, i);
				break;
			case Fi:
				e ?? x(t, r, i, s);
				break;
			case J:
				ie(e, t, r, i, a, o, s, c, l);
				break;
			default: f & 1 ? w(e, t, r, i, a, o, s, c, l) : f & 6 ? O(e, t, r, i, a, o, s, c, l) : (f & 64 || f & 128) && u.process(e, t, r, i, a, o, s, c, l, A);
		}
		d != null && a ? er(d, e && e.ref, o, t || e, !t) : d == null && e && e.ref != null && er(e.ref, null, o, e, !0);
	}, y = (e, t, n, r) => {
		if (e == null) o(t.el = u(t.children), n, r);
		else {
			let n = t.el = e.el;
			t.children !== e.children && f(n, t.children);
		}
	}, b = (e, t, n, r) => {
		e == null ? o(t.el = d(t.children || ""), n, r) : t.el = e.el;
	}, x = (e, t, n, r) => {
		[e.el, e.anchor] = _(e.children, t, n, r, e.el, e.anchor);
	}, S = ({ el: e, anchor: t }, n, r) => {
		let i;
		for (; e && e !== t;) i = h(e), o(e, n, r), e = i;
		o(t, n, r);
	}, C = ({ el: e, anchor: t }) => {
		let n;
		for (; e && e !== t;) n = h(e), s(e), e = n;
		s(t);
	}, w = (e, t, n, r, i, a, o, s, c) => {
		if (t.type === "svg" ? o = "svg" : t.type === "math" && (o = "mathml"), e == null) ee(t, n, r, i, a, o, s, c);
		else {
			let n = e.el && e.el._isVueCE ? e.el : null;
			try {
				n && n._beginPatch(), ne(e, t, i, a, o, s, c);
			} finally {
				n && n._endPatch();
			}
		}
	}, ee = (e, t, n, r, i, a, s, u) => {
		let d, f, { props: m, shapeFlag: h, transition: g, dirs: _ } = e;
		if (d = e.el = l(e.type, a, m && m.is, m), h & 8 ? p(d, e.children) : h & 16 && E(e.children, d, null, r, i, wi(e, a), s, u), _ && Nn(e, null, r, "created"), te(d, e, e.scopeId, s, r), m) {
			for (let e in m) e !== "value" && !T(e) && c(d, e, null, m[e], a, r);
			"value" in m && c(d, "value", null, m.value, a), (f = m.onVnodeBeforeMount) && ea(f, r, e);
		}
		__VUE_PROD_DEVTOOLS__ && (k(d, "__vnode", e, !0), k(d, "__vueParentComponent", r, !0)), _ && Nn(e, null, r, "beforeMount");
		let v = Ei(i, g);
		v && g.beforeEnter(d), o(d, t, n), ((f = m && m.onVnodeMounted) || v || _) && q(() => {
			try {
				f && ea(f, r, e), v && g.enter(d), _ && Nn(e, null, r, "mounted");
			} finally {}
		}, i);
	}, te = (e, t, n, r, i) => {
		if (n && g(e, n), r) for (let t = 0; t < r.length; t++) g(e, r[t]);
		if (i) {
			let n = i.subTree;
			if (t === n || Mi(n.type) && (n.ssContent === t || n.ssFallback === t)) {
				let t = i.vnode;
				te(e, t, t.scopeId, t.slotScopeIds, i.parent);
			}
		}
	}, E = (e, t, n, r, i, a, o, s, c = 0) => {
		for (let l = c; l < e.length; l++) {
			let c = e[l] = s ? Zi(e[l]) : Xi(e[l]);
			v(null, c, t, n, r, i, a, o, s);
		}
	}, ne = (e, n, r, i, a, o, s) => {
		let l = n.el = e.el;
		__VUE_PROD_DEVTOOLS__ && (l.__vnode = n);
		let { patchFlag: u, dynamicChildren: d, dirs: f } = n;
		u |= e.patchFlag & 16;
		let m = e.props || t, h = n.props || t, g;
		if (r && Ti(r, !1), (g = h.onVnodeBeforeUpdate) && ea(g, r, n, e), f && Nn(n, e, r, "beforeUpdate"), r && Ti(r, !0), d && (!e.dynamicChildren || e.dynamicChildren.length !== d.length) && (u = 0, s = !1, d = null), (m.innerHTML && h.innerHTML == null || m.textContent && h.textContent == null) && p(l, ""), d ? D(e.dynamicChildren, d, l, r, i, wi(n, a), o) : s || de(e, n, l, null, r, i, wi(n, a), o, !1), u > 0) {
			if (u & 16) re(l, m, h, r, a);
			else if (u & 2 && m.class !== h.class && c(l, "class", null, h.class, a), u & 4 && c(l, "style", m.style, h.style, a), u & 8) {
				let e = n.dynamicProps;
				for (let t = 0; t < e.length; t++) {
					let n = e[t], i = m[n], o = h[n];
					(o !== i || n === "value") && c(l, n, i, o, a, r);
				}
			}
			u & 1 && e.children !== n.children && p(l, n.children);
		} else !s && d == null && re(l, m, h, r, a);
		((g = h.onVnodeUpdated) || f) && q(() => {
			g && ea(g, r, n, e), f && Nn(n, e, r, "updated");
		}, i);
	}, D = (e, t, n, r, i, a, o) => {
		for (let s = 0; s < t.length; s++) {
			let c = e[s], l = t[s], u = c.el || ji(c), d = u && (c.type === J || !Hi(c, l) || c.shapeFlag & 198) ? m(u) : n;
			v(c, l, d, null, r, i, a, o, !0);
		}
	}, re = (e, n, r, i, a) => {
		if (n !== r) {
			if (n !== t) for (let t in n) !T(t) && !(t in r) && c(e, t, n[t], null, a, i);
			for (let t in r) {
				if (T(t)) continue;
				let o = r[t], s = n[t];
				o !== s && t !== "value" && c(e, t, s, o, a, i);
			}
			"value" in r && c(e, "value", n.value, r.value, a);
		}
	}, ie = (e, t, n, r, i, a, s, c, l) => {
		let d = t.el = e ? e.el : u(""), f = t.anchor = e ? e.anchor : u(""), { patchFlag: p, dynamicChildren: m, slotScopeIds: h } = t;
		h && (c = c ? c.concat(h) : h), e == null ? (o(d, n, r), o(f, n, r), E(t.children || [], n, f, i, a, s, c, l)) : p > 0 && p & 64 && m && e.dynamicChildren && e.dynamicChildren.length === m.length ? (D(e.dynamicChildren, m, n, i, a, s, c), (t.key != null || i && t === i.subTree) && Di(e, t, !0)) : de(e, t, n, f, i, a, s, c, l);
	}, O = (e, t, n, r, i, a, o, s, c) => {
		t.slotScopeIds = s, e == null ? t.shapeFlag & 512 ? i.ctx.activate(t, n, r, o, c) : oe(t, n, r, i, a, o, c) : se(e, t, c);
	}, oe = (e, t, n, r, i, a, o) => {
		let s = e.component = ia(e, r, i);
		if (rr(e) && (s.ctx.renderer = A), fa(s, !1, o), s.asyncDep) {
			if (i && i.registerDep(s, le, o, a), !e.el) {
				let r = s.subTree = X(Y);
				b(null, r, t, n), e.placeholder = r.el;
			}
		} else le(s, e, t, n, i, a, o);
	}, se = (e, t, n) => {
		let r = t.component = e.component;
		if ($r(e, t, n)) {
			if (r.asyncDep && !r.asyncResolved) {
				t.el = e.el, t.placeholder = e.placeholder, ue(r, t, n);
				return;
			}
			r.next = t, r.update();
		} else t.el = e.el, t.placeholder = e.placeholder, r.vnode = t;
	}, le = (e, t, n, r, i, a, o) => {
		let s = () => {
			if (e.isMounted) {
				let { next: t, bu: n, u: r, parent: s, vnode: c } = e;
				{
					let n = ki(e);
					if (n) {
						t && (t.el = c.el, ue(e, t, o)), n.asyncDep.then(() => {
							q(() => {
								e.isUnmounted || l();
							}, i);
						});
						return;
					}
				}
				let u = t, d;
				Ti(e, !1), t ? (t.el = c.el, ue(e, t, o)) : t = c, n && ae(n), (d = t.props && t.props.onVnodeBeforeUpdate) && ea(d, s, t, c), Ti(e, !0);
				let f = Yr(e), p = e.subTree;
				e.subTree = f, v(p, f, m(p.el), be(p), e, i, a), t.el = f.el, u === null && ni(e, f.el), r && q(r, i), (d = t.props && t.props.onVnodeUpdated) && q(() => ea(d, s, t, c), i), __VUE_PROD_DEVTOOLS__ && Tn(e);
			} else {
				let o, { el: s, props: c } = t, { bm: l, m: u, parent: d, root: f, type: p } = e, m = nr(t);
				if (Ti(e, !1), l && ae(l), !m && (o = c && c.onVnodeBeforeMount) && ea(o, d, t), Ti(e, !0), s && Ce) {
					let t = () => {
						e.subTree = Yr(e), Ce(s, e.subTree, e, i, null);
					};
					m && p.__asyncHydrate ? p.__asyncHydrate(s, e, t) : t();
				} else {
					f.ce && f.ce._hasShadowRoot() && f.ce._injectChildStyle(p, e.parent ? e.parent.type : void 0);
					let o = e.subTree = Yr(e);
					v(null, o, n, r, e, i, a), t.el = o.el;
				}
				if (u && q(u, i), !m && (o = c && c.onVnodeMounted)) {
					let e = t;
					q(() => ea(o, d, e), i);
				}
				(t.shapeFlag & 256 || d && nr(d.vnode) && d.vnode.shapeFlag & 256) && e.a && q(e.a, i), e.isMounted = !0, __VUE_PROD_DEVTOOLS__ && wn(e), t = n = r = null;
			}
		};
		e.scope.on();
		let c = e.effect = new Te(s);
		e.scope.off();
		let l = e.update = c.run.bind(c), u = e.job = c.runIfDirty.bind(c);
		u.i = e, u.id = e.uid, c.scheduler = () => ln(u), Ti(e, !0), l();
	}, ue = (e, t, n) => {
		t.component = e;
		let r = e.vnode.props;
		e.vnode = t, e.next = null, si(e, t.props, r, n), bi(e, t.children, n), N(), fn(e), P();
	}, de = (e, t, n, r, i, a, o, s, c = !1) => {
		let l = e && e.children, u = e ? e.shapeFlag : 0, d = t.children, { patchFlag: f, shapeFlag: m } = t;
		if (f > 0) {
			if (f & 128) {
				pe(l, d, n, r, i, a, o, s, c);
				return;
			}
			if (f & 256) {
				fe(l, d, n, r, i, a, o, s, c);
				return;
			}
		}
		m & 8 ? (u & 16 && ye(l, i, a), d !== l && p(n, d)) : u & 16 ? m & 16 ? pe(l, d, n, r, i, a, o, s, c) : ye(l, i, a, !0) : (u & 8 && p(n, ""), m & 16 && E(d, n, r, i, a, o, s, c));
	}, fe = (e, t, r, i, a, o, s, c, l) => {
		e ||= n, t ||= n;
		let u = e.length, d = t.length, f = Math.min(u, d), p = 0;
		for (; p < f; p++) {
			let n = t[p] = l ? Zi(t[p]) : Xi(t[p]);
			v(e[p], n, r, null, a, o, s, c, l);
		}
		u > d ? ye(e, a, o, !0, !1, f) : E(t, r, i, a, o, s, c, l, f);
	}, pe = (e, t, r, i, a, o, s, c, l) => {
		let u = 0, d = t.length, f = e.length - 1, p = d - 1;
		for (; u <= f && u <= p;) {
			let n = e[u], i = t[u] = l ? Zi(t[u]) : Xi(t[u]);
			if (Hi(n, i)) v(n, i, r, null, a, o, s, c, l);
			else break;
			u++;
		}
		for (; u <= f && u <= p;) {
			let n = e[f], i = t[p] = l ? Zi(t[p]) : Xi(t[p]);
			if (Hi(n, i)) v(n, i, r, null, a, o, s, c, l);
			else break;
			f--, p--;
		}
		if (u > f) {
			if (u <= p) {
				let e = p + 1, n = e < d ? t[e].el : i;
				for (; u <= p;) v(null, t[u] = l ? Zi(t[u]) : Xi(t[u]), r, n, a, o, s, c, l), u++;
			}
		} else if (u > p) for (; u <= f;) he(e[u], a, o, !0), u++;
		else {
			let m = u, h = u, g = /* @__PURE__ */ new Map();
			for (u = h; u <= p; u++) {
				let e = t[u] = l ? Zi(t[u]) : Xi(t[u]);
				e.key != null && g.set(e.key, u);
			}
			let _, y = 0, b = p - h + 1, x = !1, S = 0, C = Array(b);
			for (u = 0; u < b; u++) C[u] = 0;
			for (u = m; u <= f; u++) {
				let n = e[u];
				if (y >= b) {
					he(n, a, o, !0);
					continue;
				}
				let i;
				if (n.key != null) i = g.get(n.key);
				else for (_ = h; _ <= p; _++) if (C[_ - h] === 0 && Hi(n, t[_])) {
					i = _;
					break;
				}
				i === void 0 ? he(n, a, o, !0) : (C[i - h] = u + 1, i >= S ? S = i : x = !0, v(n, t[i], r, null, a, o, s, c, l), y++);
			}
			let w = x ? Oi(C) : n;
			for (_ = w.length - 1, u = b - 1; u >= 0; u--) {
				let e = h + u, n = t[e], f = t[e + 1], p = e + 1 < d ? f.el || ji(f) : i;
				C[u] === 0 ? v(null, n, r, p, a, o, s, c, l) : x && (_ < 0 || u !== w[_] ? me(n, r, p, 2) : _--);
			}
		}
	}, me = (e, t, n, r, i = null) => {
		let { el: a, type: c, transition: l, children: u, shapeFlag: d } = e;
		if (d & 6) me(e.component.subTree, t, n, r);
		else if (d & 128) e.suspense.move(t, n, r);
		else if (d & 64) c.move(e, t, n, A);
		else if (c === J) {
			o(a, t, n);
			for (let e = 0; e < u.length; e++) me(u[e], t, n, r);
			o(e.anchor, t, n);
		} else if (c === Fi) S(e, t, n);
		else if (r !== 2 && d & 1 && l) {
			if (r === 0) l.persisted && !a[Gn] ? o(a, t, n) : (l.beforeEnter(a), o(a, t, n), q(() => l.enter(a), i));
			else {
				let { leave: r, delayLeave: i, afterLeave: c } = l, u = () => {
					e.ctx.isUnmounted ? s(a) : o(a, t, n);
				}, d = () => {
					let e = a._isLeaving || !!a[Gn];
					a._isLeaving && a[Gn](!0), l.persisted && !e ? u() : r(a, () => {
						u(), c && c();
					});
				};
				i ? i(a, u, d) : d();
			}
		} else o(a, t, n);
	}, he = (e, t, n, r = !1, i = !1) => {
		let { type: a, props: o, ref: s, children: c, dynamicChildren: l, shapeFlag: u, patchFlag: d, dirs: f, cacheIndex: p, memo: m } = e;
		if ((d === -2 || l && l.hasOnce) && (i = !1), s != null && (N(), er(s, null, n, e, !0), P()), p == null || e.ctx && e.ctx !== t || (t.renderCache[p] = void 0), u & 256) {
			t.ctx.deactivate(e);
			return;
		}
		let h = u & 1 && f, g = !nr(e), _;
		if (g && (_ = o && o.onVnodeBeforeUnmount) && ea(_, t, e), u & 6) ve(e.component, n, r);
		else {
			if (u & 128) {
				e.suspense.unmount(n, r);
				return;
			}
			h && Nn(e, null, t, "beforeUnmount"), u & 64 ? e.type.remove(e, t, n, A, r) : l && !l.hasOnce && (a !== J || d > 0 && d & 64) ? ye(l, t, n, !1, !0) : (a === J && d & 384 || !i && u & 16) && ye(c, t, n), r && ge(e);
		}
		let v = m != null && p == null;
		(g && (_ = o && o.onVnodeUnmounted) || h || v) && q(() => {
			_ && ea(_, t, e), h && Nn(e, null, t, "unmounted"), v && (e.el = null);
		}, n);
	}, ge = (e) => {
		let { type: t, el: n, anchor: r, transition: i } = e, a = t === J;
		if (a && !i) {
			_e(n, r);
			return;
		}
		if (t === Fi) {
			C(e), i && !i.persisted && i.afterLeave && i.afterLeave();
			return;
		}
		let o = a ? Yn(e) : e, c = () => {
			a ? m(n) && _e(n, r) : s(n), i && !i.persisted && i.afterLeave && i.afterLeave();
		};
		if (o && o.shapeFlag & 1 && i && !i.persisted) {
			let { leave: e, delayLeave: t } = i, n = o.el, r = () => e(n, c);
			t ? t(n, c, r) : r();
		} else c();
	}, _e = (e, t) => {
		let n;
		for (; e !== t;) n = h(e), s(e), e = n;
		s(t);
	}, ve = (e, t, n) => {
		let { bum: r, scope: i, job: a, subTree: o, um: s, m: c, a: l } = e;
		Ai(c), Ai(l), r && ae(r), i.stop(), a ? (a.flags |= 8, he(o, e, t, n)) : e.vnode.el && o && (o.transition = e.vnode.transition, he(o, e, t, n)), s && q(s, t), q(() => {
			e.isUnmounted = !0;
		}, t), __VUE_PROD_DEVTOOLS__ && Dn(e);
	}, ye = (e, t, n, r = !1, i = !1, a = 0) => {
		for (let o = a; o < e.length; o++) he(e[o], t, n, r, i);
	}, be = (e) => {
		if (e.shapeFlag & 6) return be(e.component.subTree);
		if (e.shapeFlag & 128) return e.suspense.next();
		let t = h(e.anchor || e.el), n = t && t[Un];
		return n ? h(n) : t;
	}, xe = (e, t, n) => {
		hn(() => {
			e == null ? t._vnode && he(t._vnode, null, null, !0) : v(t._vnode || null, e, t, null, null, null, n), t._vnode = e;
		}, e && t._vnode ? void 0 : e && e.component ? e.component.uid : ra());
	}, A = {
		p: v,
		um: he,
		m: me,
		r: ge,
		mt: oe,
		mc: E,
		pc: de,
		pbc: D,
		n: be,
		o: e
	}, Se, Ce;
	return i && ([Se, Ce] = i(A)), {
		render: xe,
		hydrate: Se,
		createApp: Hr(xe, Se)
	};
}
function wi({ type: e, props: t }, n) {
	return n === "svg" && e === "foreignObject" || n === "mathml" && e === "annotation-xml" && t && t.encoding && t.encoding.includes("html") ? void 0 : n;
}
function Ti({ effect: e, job: t }, n) {
	n ? (e.flags |= 32, t.flags |= 4) : (e.flags &= -33, t.flags &= -5);
}
function Ei(e, t) {
	return (!e || e && !e.pendingBranch) && t && !t.persisted;
}
function Di(e, t, n = !1) {
	let r = e.children, i = t.children;
	if (d(r) && d(i)) for (let e = 0; e < r.length; e++) {
		let t = r[e], a = i[e];
		a.shapeFlag & 1 && !a.dynamicChildren && ((a.patchFlag <= 0 || a.patchFlag === 32) && (a = i[e] = Zi(i[e]), a.el = t.el), !n && a.patchFlag !== -2 && Di(t, a)), a.type === Pi && (a.patchFlag === -1 && (a = i[e] = Zi(a)), a.el = t.el), a.type === Y && !a.el && (a.el = t.el);
	}
}
function Oi(e) {
	let t = e.slice(), n = [0], r, i, a, o, s, c = e.length;
	for (r = 0; r < c; r++) {
		let c = e[r];
		if (c !== 0) {
			if (i = n[n.length - 1], e[i] < c) {
				t[r] = i, n.push(r);
				continue;
			}
			for (a = 0, o = n.length - 1; a < o;) s = a + o >> 1, e[n[s]] < c ? a = s + 1 : o = s;
			c < e[n[a]] && (a > 0 && (t[r] = n[a - 1]), n[a] = r);
		}
	}
	for (a = n.length, o = n[a - 1]; a-- > 0;) n[a] = o, o = t[o];
	return n;
}
function ki(e) {
	let t = e.subTree.component;
	if (t) return t.asyncDep && !t.asyncResolved ? t : ki(t);
}
function Ai(e) {
	if (e) for (let t = 0; t < e.length; t++) e[t].flags |= 8;
}
function ji(e) {
	if (e.placeholder) return e.placeholder;
	let t = e.component;
	return t ? ji(t.subTree) : null;
}
var Mi = (e) => e.__isSuspense;
function Ni(e, t) {
	t && t.pendingBranch ? d(e) ? t.effects.push(...e) : t.effects.push(e) : dn(e);
}
var J = /* @__PURE__ */ Symbol.for("v-fgt"), Pi = /* @__PURE__ */ Symbol.for("v-txt"), Y = /* @__PURE__ */ Symbol.for("v-cmt"), Fi = /* @__PURE__ */ Symbol.for("v-stc"), Ii = [], Li = null;
function Ri() {
	Ii.pop(), Li = Ii[Ii.length - 1] || null;
}
var zi = 1;
function Bi(e, t = !1) {
	zi += e, e < 0 && Li && t && (Li.hasOnce = !0);
}
function Vi(e) {
	return e ? e.__v_isVNode === !0 : !1;
}
function Hi(e, t) {
	return e.type === t.type && e.key === t.key;
}
var Ui = ({ key: e }) => e ?? null, Wi = ({ ref: e, ref_key: t, ref_for: n }) => (typeof e == "number" && (e = "" + e), e == null ? null : g(e) || /* @__PURE__ */ z(e) || h(e) ? {
	i: G,
	r: e,
	k: t,
	f: !!n
} : e);
function Gi(e, t = null, n = null, r = 0, i = null, a = e === J ? 0 : 1, o = !1, s = !1) {
	let c = {
		__v_isVNode: !0,
		__v_skip: !0,
		type: e,
		props: t,
		key: t && Ui(t),
		ref: t && Wi(t),
		scopeId: An,
		slotScopeIds: null,
		children: n,
		component: null,
		suspense: null,
		ssContent: null,
		ssFallback: null,
		dirs: null,
		transition: null,
		el: null,
		anchor: null,
		target: null,
		targetStart: null,
		targetAnchor: null,
		staticCount: 0,
		shapeFlag: a,
		patchFlag: r,
		dynamicProps: i,
		dynamicChildren: null,
		appContext: null,
		ctx: G
	};
	return s ? (Qi(c, n), a & 128 && e.normalize(c)) : n && (c.shapeFlag |= g(n) ? 8 : 16), zi > 0 && !o && Li && (c.patchFlag > 0 || a & 6) && c.patchFlag !== 32 && Li.push(c), c;
}
var X = Ki;
function Ki(e, t = null, n = null, r = 0, i = null, a = !1) {
	if (e && e !== br || (e = Y), Vi(e)) {
		let r = Ji(e, t, !0);
		return n && Qi(r, n), zi > 0 && !a && Li && (r.shapeFlag & 6 ? Li[Li.indexOf(e)] = r : Li.push(r)), r.patchFlag = -2, r;
	}
	if (ya(e) && (e = e.__vccOpts), t) {
		t = qi(t);
		let { class: e, style: n } = t;
		e && !g(e) && (t.class = me(e)), v(n) && (/* @__PURE__ */ Ft(n) && !d(n) && (n = s({}, n)), t.style = le(n));
	}
	let o = g(e) ? 1 : Mi(e) ? 128 : Wn(e) ? 64 : v(e) ? 4 : h(e) ? 2 : 0;
	return Gi(e, t, n, r, i, o, a, !0);
}
function qi(e) {
	return e ? /* @__PURE__ */ Ft(e) || ai(e) ? s({}, e) : e : null;
}
function Ji(e, t, n = !1, r = !1) {
	let { props: i, ref: a, patchFlag: o, children: s, transition: c } = e, l = t ? $i(i || {}, t) : i, u = {
		__v_isVNode: !0,
		__v_skip: !0,
		type: e.type,
		props: l,
		key: l && Ui(l),
		ref: t && t.ref ? n && a ? d(a) ? a.concat(Wi(t)) : [a, Wi(t)] : Wi(t) : a,
		scopeId: e.scopeId,
		slotScopeIds: e.slotScopeIds,
		children: s,
		target: e.target,
		targetStart: e.targetStart,
		targetAnchor: e.targetAnchor,
		staticCount: e.staticCount,
		shapeFlag: e.shapeFlag,
		patchFlag: t && e.type !== J ? o === -1 ? 16 : o | 16 : o,
		dynamicProps: e.dynamicProps,
		dynamicChildren: e.dynamicChildren,
		appContext: e.appContext,
		dirs: e.dirs,
		transition: c,
		component: e.component,
		suspense: e.suspense,
		ssContent: e.ssContent && Ji(e.ssContent),
		ssFallback: e.ssFallback && Ji(e.ssFallback),
		placeholder: e.placeholder,
		el: e.el,
		anchor: e.anchor,
		ctx: e.ctx,
		ce: e.ce,
		cacheIndex: e.cacheIndex
	};
	return c && r && Jn(u, c.clone(u)), u;
}
function Yi(e = " ", t = 0) {
	return X(Pi, null, e, t);
}
function Xi(e) {
	return e == null || typeof e == "boolean" ? X(Y) : d(e) ? X(J, null, e.slice()) : Vi(e) ? Zi(e) : X(Pi, null, String(e));
}
function Zi(e) {
	return e.el === null && e.patchFlag !== -1 || e.memo ? e : Ji(e);
}
function Qi(e, t) {
	let n = 0, { shapeFlag: r } = e;
	if (t == null) t = null;
	else if (d(t)) n = 16;
	else if (typeof t == "object") {
		if (r & 65) {
			let n = t.default;
			n && (n._c && (n._d = !1), Qi(e, n()), n._c && (n._d = !0));
			return;
		}
		{
			n = 32;
			let r = t._;
			!r && !ai(t) ? t._ctx = G : r === 3 && G && (G.slots._ === 1 ? t._ = 1 : (t._ = 2, e.patchFlag |= 1024));
		}
	} else if (h(t)) {
		if (r & 65) {
			Qi(e, { default: t });
			return;
		}
		t = {
			default: t,
			_ctx: G
		}, n = 32;
	} else t = String(t), r & 64 ? (n = 16, t = [Yi(t)]) : n = 8;
	e.children = t, e.shapeFlag |= n;
}
function $i(...e) {
	let t = {};
	for (let n = 0; n < e.length; n++) {
		let r = e[n];
		for (let e in r) if (e === "class") t.class !== r.class && (t.class = me([t.class, r.class]));
		else if (e === "style") t.style = le([t.style, r.style]);
		else if (a(e)) {
			let n = t[e], i = r[e];
			i && n !== i && !(d(n) && n.includes(i)) ? t[e] = n ? [].concat(n, i) : i : i == null && n == null && !o(e) && (t[e] = i);
		} else e !== "" && (t[e] = r[e]);
	}
	return t;
}
function ea(e, t, n, r = null) {
	B(e, t, 7, [n, r]);
}
var ta = Br(), na = 0;
function ra() {
	return na;
}
function ia(e, n, r) {
	let i = e.type, a = (n ? n.appContext : e.appContext) || ta, o = {
		uid: na++,
		vnode: e,
		type: i,
		parent: n,
		appContext: a,
		root: null,
		next: null,
		subTree: null,
		effect: null,
		update: null,
		job: null,
		scope: new Se(!0),
		render: null,
		proxy: null,
		exposed: null,
		exposeProxy: null,
		withProxy: null,
		provides: n ? n.provides : Object.create(a.provides),
		ids: n ? n.ids : [
			"",
			0,
			0
		],
		accessCache: null,
		renderCache: [],
		components: null,
		directives: null,
		propsOptions: di(i, a),
		emitsOptions: qr(i, a),
		emit: null,
		emitted: null,
		propsDefaults: t,
		inheritAttrs: i.inheritAttrs,
		ctx: t,
		data: t,
		props: t,
		attrs: t,
		slots: t,
		refs: t,
		setupState: t,
		setupContext: null,
		suspense: r,
		suspenseId: r ? r.pendingId : 0,
		asyncDep: null,
		asyncResolved: !1,
		isMounted: !1,
		isUnmounted: !1,
		isDeactivated: !1,
		bc: null,
		c: null,
		bm: null,
		m: null,
		bu: null,
		u: null,
		um: null,
		bum: null,
		da: null,
		a: null,
		rtg: null,
		rtc: null,
		ec: null,
		sp: null
	};
	return o.ctx = { _: o }, o.root = n ? n.root : o, o.emit = Gr.bind(null, o), e.ce && e.ce(o), o;
}
var Z = null, aa = () => Z || G, oa, sa;
{
	let e = ce(), t = (t, n) => {
		let r;
		return (r = e[t]) || (r = e[t] = []), r.push(n), (e) => {
			r.length > 1 ? r.forEach((t) => t(e)) : r[0](e);
		};
	};
	oa = t("__VUE_INSTANCE_SETTERS__", (e) => Z = e), sa = t("__VUE_SSR_SETTERS__", (e) => da = e);
}
var ca = (e) => {
	let t = Z;
	return oa(e), e.scope.on(), () => {
		e.scope.off(), oa(t);
	};
}, la = () => {
	Z && Z.scope.off(), oa(null);
};
function ua(e) {
	return e.vnode.shapeFlag & 4;
}
var da = !1;
function fa(e, t = !1, n = !1) {
	t && sa(t);
	let { props: r, children: i } = e.vnode, a = ua(e);
	oi(e, r, a, t), yi(e, i, n || t);
	let o = a ? pa(e, t) : void 0;
	return t && sa(!1), o;
}
function pa(e, t) {
	let n = e.type;
	e.accessCache = /* @__PURE__ */ Object.create(null), e.proxy = new Proxy(e.ctx, wr);
	let { setup: r } = n;
	if (r) {
		N();
		let n = e.setupContext = r.length > 1 ? _a(e) : null, i = ca(e), a = $t(r, e, 0, [e.props, n]), o = y(a);
		if (P(), i(), (o || e.sp) && !nr(e) && Zn(e), o) {
			if (a.then(la, la), t) return a.then((n) => {
				sa(!0);
				try {
					ma(e, n, t);
				} finally {
					sa(!1);
				}
			}).catch((t) => {
				en(t, e, 0);
			});
			e.asyncDep = a;
		} else ma(e, a, t);
	} else ha(e, t);
}
function ma(e, t, n) {
	h(t) ? e.type.__ssrInlineRender ? e.ssrRender = t : e.render = t : v(t) && (__VUE_PROD_DEVTOOLS__ && (e.devtoolsRawSetupState = t), e.setupState = Wt(t)), ha(e, n);
}
function ha(e, t, n) {
	let i = e.type;
	if (e.render ||= i.render || r, __VUE_OPTIONS_API__) {
		let t = ca(e);
		N();
		try {
			Dr(e);
		} finally {
			P(), t();
		}
	}
}
var ga = { get(e, t) {
	return F(e, "get", ""), e[t];
} };
function _a(e) {
	return {
		attrs: new Proxy(e.attrs, ga),
		slots: e.slots,
		emit: e.emit,
		expose: (t) => {
			e.exposed = t || {};
		}
	};
}
function va(e) {
	return e.exposed ? e.exposeProxy ||= new Proxy(Wt(It(e.exposed)), {
		get(t, n) {
			if (n in t) return t[n];
			if (n in Sr) return Sr[n](e);
		},
		has(e, t) {
			return t in e || t in Sr;
		}
	}) : e.proxy;
}
function ya(e) {
	return h(e) && "__vccOpts" in e;
}
var ba = (e, t) => /* @__PURE__ */ Kt(e, t, da);
function xa(e, t, n) {
	try {
		Bi(-1);
		let r = arguments.length;
		return r === 2 ? v(t) && !d(t) ? Vi(t) ? X(e, null, [t]) : X(e, t) : X(e, null, t) : (r > 3 ? n = Array.prototype.slice.call(arguments, 2) : r === 3 && Vi(n) && (n = [n]), X(e, t, n));
	} finally {
		Bi(1);
	}
}
var Sa = "3.5.44", Ca = void 0, wa = typeof window < "u" && window.trustedTypes;
if (wa) try {
	Ca = /* @__PURE__ */ wa.createPolicy("vue", { createHTML: (e) => e });
} catch {}
var Ta = Ca ? (e) => Ca.createHTML(e) : (e) => e, Ea = "http://www.w3.org/2000/svg", Da = "http://www.w3.org/1998/Math/MathML", Oa = typeof document < "u" ? document : null, ka = Oa && /* @__PURE__ */ Oa.createElement("template"), Aa = {
	insert: (e, t, n) => {
		t.insertBefore(e, n || null);
	},
	remove: (e) => {
		let t = e.parentNode;
		t && t.removeChild(e);
	},
	createElement: (e, t, n, r) => {
		let i = t === "svg" ? Oa.createElementNS(Ea, e) : t === "mathml" ? Oa.createElementNS(Da, e) : n ? Oa.createElement(e, { is: n }) : Oa.createElement(e);
		return e === "select" && r && r.multiple != null && i.setAttribute("multiple", r.multiple), i;
	},
	createText: (e) => Oa.createTextNode(e),
	createComment: (e) => Oa.createComment(e),
	setText: (e, t) => {
		e.nodeValue = t;
	},
	setElementText: (e, t) => {
		e.textContent = t;
	},
	parentNode: (e) => e.parentNode,
	nextSibling: (e) => e.nextSibling,
	querySelector: (e) => Oa.querySelector(e),
	setScopeId(e, t) {
		e.setAttribute(t, "");
	},
	insertStaticContent(e, t, n, r, i, a) {
		let o = n ? n.previousSibling : t.lastChild;
		if (i && (i === a || i.nextSibling)) for (; t.insertBefore(i.cloneNode(!0), n), i !== a && (i = i.nextSibling););
		else {
			ka.innerHTML = Ta(r === "svg" ? `<svg>${e}</svg>` : r === "mathml" ? `<math>${e}</math>` : e);
			let i = ka.content;
			if (r === "svg" || r === "mathml") {
				let e = i.firstChild;
				for (; e.firstChild;) i.appendChild(e.firstChild);
				i.removeChild(e);
			}
			t.insertBefore(i, n);
		}
		return [o ? o.nextSibling : t.firstChild, n ? n.previousSibling : t.lastChild];
	}
}, ja = /* @__PURE__ */ Symbol("_vtc");
function Ma(e, t, n) {
	let r = e[ja];
	r && (t = (t ? [t, ...r] : [...r]).join(" ")), t == null ? e.removeAttribute("class") : n ? e.setAttribute("class", t) : e.className = t;
}
var Na = /* @__PURE__ */ Symbol("_vod"), Pa = /* @__PURE__ */ Symbol("_vsh"), Fa = /* @__PURE__ */ Symbol(""), Ia = /(?:^|;)\s*display\s*:/;
function La(e, t, n) {
	let r = e.style, i = g(n), a = !1;
	if (n && !i) {
		if (t) {
			if (g(t)) for (let e of t.split(";")) {
				let t = e.slice(0, e.indexOf(":")).trim();
				n[t] ?? za(r, t, "");
			}
			else for (let e in t) n[e] ?? za(r, e, "");
		}
		for (let i in n) {
			i === "display" && (a = !0);
			let o = n[i];
			o == null ? za(r, i, "") : Ua(e, i, !g(t) && t ? t[i] : void 0, o) || za(r, i, o);
		}
	} else if (i) {
		if (t !== n) {
			let e = r[Fa];
			e && (n += ";" + e), r.cssText = n, a = Ia.test(n);
		}
	} else t && e.removeAttribute("style");
	Na in e && (i && t === n || (e[Na] = a ? r.display : ""), e[Pa] && (r.display = "none"));
}
var Ra = /\s*!important$/;
function za(e, t, n) {
	if (d(n)) n.forEach((n) => za(e, t, n));
	else if (n ??= "", t.startsWith("--")) Ra.test(n) ? e.setProperty(t, n.replace(Ra, ""), "important") : e.setProperty(t, n);
	else {
		let r = Ha(e, t);
		Ra.test(n) ? e.setProperty(D(r), n.replace(Ra, ""), "important") : e[r] = n;
	}
}
var Ba = [
	"Webkit",
	"Moz",
	"ms"
], Va = {};
function Ha(e, t) {
	let n = Va[t];
	if (n) return n;
	let r = E(t);
	if (r !== "filter" && r in e) return Va[t] = r;
	r = re(r);
	for (let n = 0; n < Ba.length; n++) {
		let i = Ba[n] + r;
		if (i in e) return Va[t] = i;
	}
	return t;
}
function Ua(e, t, n, r) {
	return e.tagName === "TEXTAREA" && (t === "width" || t === "height") && g(r) && n === r;
}
var Wa = "http://www.w3.org/1999/xlink";
function Ga(e, t, n, r, i, a = he(t)) {
	r && t.startsWith("xlink:") ? n == null ? e.removeAttributeNS(Wa, t.slice(6, t.length)) : e.setAttributeNS(Wa, t, n) : n == null || a && !ge(n) ? e.removeAttribute(t) : e.setAttribute(t, a ? "" : _(n) ? String(n) : n);
}
function Ka(e, t, n, r, i) {
	if (t === "innerHTML" || t === "textContent") {
		n != null && (e[t] = t === "innerHTML" ? Ta(n) : n);
		return;
	}
	let a = e.tagName;
	if (t === "value" && a !== "PROGRESS" && !a.includes("-")) {
		let r = a === "OPTION" ? e.getAttribute("value") || "" : e.value, i = n == null ? e.type === "checkbox" ? "on" : "" : String(n);
		r === i && "_value" in e || (e.value = i), n ?? e.removeAttribute(t), e._value = n;
		return;
	}
	let o = !1;
	if (n === "" || n == null) {
		let r = typeof e[t];
		r === "boolean" ? n = ge(n) : n == null && r === "string" ? (n = "", o = !0) : r === "number" && (n = 0, o = !0);
	}
	try {
		e[t] = n;
	} catch {}
	o && e.removeAttribute(i || t);
}
function qa(e, t, n, r) {
	e.addEventListener(t, n, r);
}
function Ja(e, t, n, r) {
	e.removeEventListener(t, n, r);
}
var Ya = /* @__PURE__ */ Symbol("_vei");
function Xa(e, t, n, r, i = null) {
	let a = e[Ya] || (e[Ya] = {}), o = a[t];
	if (r && o) o.value = r;
	else {
		let [n, s] = $a(t);
		r ? qa(e, n, a[t] = ro(r, i), s) : o && (Ja(e, n, o, s), a[t] = void 0);
	}
}
var Za = /(Once|Passive|Capture)$/, Qa = /^on:?(?:Once|Passive|Capture)$/;
function $a(e) {
	let t, n;
	for (; (n = e.match(Za)) && !Qa.test(e);) t ||= {}, e = e.slice(0, e.length - n[1].length), t[n[1].toLowerCase()] = !0;
	return [e[2] === ":" ? e.slice(3) : D(e.slice(2)), t];
}
var eo = 0, to = /* @__PURE__ */ Promise.resolve(), no = () => eo ||= (to.then(() => eo = 0), Date.now());
function ro(e, t) {
	let n = (e) => {
		if (!e._vts) e._vts = Date.now();
		else if (e._vts <= n.attached) return;
		let r = n.value;
		if (d(r)) {
			let n = e.stopImmediatePropagation;
			e.stopImmediatePropagation = () => {
				n.call(e), e._stopped = !0;
			};
			let i = r.slice(), a = [e];
			for (let n = 0; n < i.length && !e._stopped; n++) {
				let e = i[n];
				e && B(e, t, 5, a);
			}
		} else B(r, t, 5, [e]);
	};
	return n.value = e, n.attached = no(), n;
}
var io = (e) => e.charCodeAt(0) === 111 && e.charCodeAt(1) === 110 && e.charCodeAt(2) > 96 && e.charCodeAt(2) < 123, ao = (e, t, n, r, i, s) => {
	let c = i === "svg";
	t === "class" ? Ma(e, r, c) : t === "style" ? La(e, n, r) : a(t) ? o(t) || Xa(e, t, n, r, s) : (t[0] === "." ? (t = t.slice(1), 1) : t[0] === "^" ? (t = t.slice(1), 0) : oo(e, t, r, c)) ? (Ka(e, t, r), e.tagName.includes("-") || t !== "value" && t !== "checked" && t !== "selected" || Ga(e, t, r, c, s, t !== "value")) : e._isVueCE && (so(e, t) || e._def.__asyncLoader && (/[A-Z]/.test(t) || !g(r))) ? Ka(e, E(t), r, s, t) : (t === "true-value" ? e._trueValue = r : t === "false-value" && (e._falseValue = r), Ga(e, t, r, c));
};
function oo(e, t, n, r) {
	if (r) return !!(t === "innerHTML" || t === "textContent" || t in e && io(t) && h(n));
	if (t === "spellcheck" || t === "draggable" || t === "translate" || t === "autocorrect" || t === "sandbox" && e.tagName === "IFRAME" || t === "form" || t === "list" && e.tagName === "INPUT" || t === "type" && e.tagName === "TEXTAREA") return !1;
	if (t === "width" || t === "height") {
		let t = e.tagName;
		if (t === "IMG" || t === "VIDEO" || t === "CANVAS" || t === "SOURCE") return !1;
	}
	return io(t) && g(n) ? !1 : t in e;
}
function so(e, t) {
	let n = e._def.props;
	if (!n) return !1;
	let r = E(t);
	return Array.isArray(n) ? n.some((e) => E(e) === r) : Object.keys(n).some((e) => E(e) === r);
}
var co = /* @__PURE__ */ s({ patchProp: ao }, Aa), lo;
function uo() {
	return lo ||= Si(co);
}
var fo = ((...e) => {
	let t = uo().createApp(...e), { mount: n } = t;
	return t.mount = (e) => {
		let r = mo(e);
		if (!r) return;
		let i = t._component;
		h(i) || i.render || i.template || (i.template = r.innerHTML), r.nodeType === 1 && (r.textContent = "");
		let a = n(r, !1, po(r));
		return r instanceof Element && (r.removeAttribute("v-cloak"), r.setAttribute("data-v-app", "")), a;
	}, t;
});
function po(e) {
	if (e instanceof SVGElement) return "svg";
	if (typeof MathMLElement == "function" && e instanceof MathMLElement) return "mathml";
}
function mo(e) {
	return g(e) ? document.querySelector(e) : e;
}
// node_modules/.pnpm/clsx@2.1.1/node_modules/clsx/dist/clsx.mjs
function ho(e) {
	var t, n, r = "";
	if (typeof e == "string" || typeof e == "number") r += e;
	else if (typeof e == "object") {
		if (Array.isArray(e)) {
			var i = e.length;
			for (t = 0; t < i; t++) e[t] && (n = ho(e[t])) && (r && (r += " "), r += n);
		} else for (n in e) e[n] && (r && (r += " "), r += n);
	}
	return r;
}
function go() {
	for (var e, t, n = 0, r = "", i = arguments.length; n < i; n++) (e = arguments[n]) && (t = ho(e)) && (r && (r += " "), r += t);
	return r;
}
// node_modules/.pnpm/@floating-ui+utils@0.2.12/node_modules/@floating-ui/utils/dist/floating-ui.utils.mjs
var _o = Math.min, vo = Math.max, yo = Math.round, bo = Math.floor, xo = (e) => ({
	x: e,
	y: e
}), So = {
	left: "right",
	right: "left",
	bottom: "top",
	top: "bottom"
};
function Co(e, t, n) {
	return vo(e, _o(t, n));
}
function wo(e, t) {
	return typeof e == "function" ? e(t) : e;
}
function To(e) {
	return e.split("-")[0];
}
function Eo(e) {
	return e.split("-")[1];
}
function Do(e) {
	return e === "x" ? "y" : "x";
}
function Oo(e) {
	return e === "y" ? "height" : "width";
}
function ko(e) {
	let t = e[0];
	return t === "t" || t === "b" ? "y" : "x";
}
function Ao(e) {
	return Do(ko(e));
}
function jo(e, t, n) {
	n === void 0 && (n = !1);
	let r = Eo(e), i = Ao(e), a = Oo(i), o = i === "x" ? r === (n ? "end" : "start") ? "right" : "left" : r === "start" ? "bottom" : "top";
	return t.reference[a] > t.floating[a] && (o = Bo(o)), [o, Bo(o)];
}
function Mo(e) {
	let t = Bo(e);
	return [
		No(e),
		t,
		No(t)
	];
}
function No(e) {
	return e.includes("start") ? e.replace("start", "end") : e.replace("end", "start");
}
var Po = ["left", "right"], Fo = ["right", "left"], Io = ["top", "bottom"], Lo = ["bottom", "top"];
function Ro(e, t, n) {
	switch (e) {
		case "top":
		case "bottom": return n ? t ? Fo : Po : t ? Po : Fo;
		case "left":
		case "right": return t ? Io : Lo;
		default: return [];
	}
}
function zo(e, t, n, r) {
	let i = Eo(e), a = Ro(To(e), n === "start", r);
	return i && (a = a.map((e) => e + "-" + i), t && (a = a.concat(a.map(No)))), a;
}
function Bo(e) {
	let t = To(e);
	return So[t] + e.slice(t.length);
}
function Vo(e) {
	return {
		top: e.top ?? 0,
		right: e.right ?? 0,
		bottom: e.bottom ?? 0,
		left: e.left ?? 0
	};
}
function Ho(e) {
	return typeof e == "number" ? {
		top: e,
		right: e,
		bottom: e,
		left: e
	} : Vo(e);
}
function Uo(e) {
	let { x: t, y: n, width: r, height: i } = e;
	return {
		width: r,
		height: i,
		top: n,
		left: t,
		right: t + r,
		bottom: n + i,
		x: t,
		y: n
	};
}
// node_modules/.pnpm/@floating-ui+core@1.8.0/node_modules/@floating-ui/core/dist/floating-ui.core.mjs
function Wo(e, t, n) {
	let { reference: r, floating: i } = e, a = ko(t), o = Ao(t), s = Oo(o), c = To(t), l = a === "y", u = r.x + r.width / 2 - i.width / 2, d = r.y + r.height / 2 - i.height / 2, f = r[s] / 2 - i[s] / 2, p;
	switch (c) {
		case "top":
			p = {
				x: u,
				y: r.y - i.height
			};
			break;
		case "bottom":
			p = {
				x: u,
				y: r.y + r.height
			};
			break;
		case "right":
			p = {
				x: r.x + r.width,
				y: d
			};
			break;
		case "left":
			p = {
				x: r.x - i.width,
				y: d
			};
			break;
		default: p = {
			x: r.x,
			y: r.y
		};
	}
	let m = Eo(t);
	return m && (p[o] += f * (m === "end" ? 1 : -1) * (n && l ? -1 : 1)), p;
}
async function Go(e, t) {
	t === void 0 && (t = {});
	let { x: n, y: r, platform: i, rects: a, elements: o, strategy: s } = e, { boundary: c = "clippingAncestors", rootBoundary: l = "viewport", elementContext: u = "floating", altBoundary: d = !1, padding: f = 0 } = wo(t, e), p = Ho(f), m = o[d ? u === "floating" ? "reference" : "floating" : u], h = Uo(await i.getClippingRect({
		element: await (i.isElement == null ? void 0 : i.isElement(m)) ?? !0 ? m : m.contextElement || await (i.getDocumentElement == null ? void 0 : i.getDocumentElement(o.floating)),
		boundary: c,
		rootBoundary: l,
		strategy: s
	})), g = u === "floating" ? {
		x: n,
		y: r,
		width: a.floating.width,
		height: a.floating.height
	} : a.reference, _ = await (i.getOffsetParent == null ? void 0 : i.getOffsetParent(o.floating)), v = await (i.isElement == null ? void 0 : i.isElement(_)) && await (i.getScale == null ? void 0 : i.getScale(_)) || {
		x: 1,
		y: 1
	}, y = Uo(i.convertOffsetParentRelativeRectToViewportRelativeRect ? await i.convertOffsetParentRelativeRectToViewportRelativeRect({
		elements: o,
		rect: g,
		offsetParent: _,
		strategy: s
	}) : g);
	return {
		top: (h.top - y.top + p.top) / v.y,
		bottom: (y.bottom - h.bottom + p.bottom) / v.y,
		left: (h.left - y.left + p.left) / v.x,
		right: (y.right - h.right + p.right) / v.x
	};
}
var Ko = 50, qo = async (e, t, n) => {
	let { placement: r = "bottom", strategy: i = "absolute", middleware: a = [], platform: o } = n, s = o.detectOverflow ? o : {
		...o,
		detectOverflow: Go
	}, c = await (o.isRTL == null ? void 0 : o.isRTL(t)), l = await o.getElementRects({
		reference: e,
		floating: t,
		strategy: i
	}), { x: u, y: d } = Wo(l, r, c), f = r, p = 0, m = {};
	for (let n = 0; n < a.length; n++) {
		let h = a[n];
		if (!h) continue;
		let { name: g, fn: _ } = h, { x: v, y, data: b, reset: x } = await _({
			x: u,
			y: d,
			initialPlacement: r,
			placement: f,
			strategy: i,
			middlewareData: m,
			rects: l,
			platform: s,
			elements: {
				reference: e,
				floating: t
			}
		});
		u = v ?? u, d = y ?? d, m[g] = {
			...m[g],
			...b
		}, x && p < Ko && (p++, typeof x == "object" && (x.placement && (f = x.placement), x.rects && (l = x.rects === !0 ? await o.getElementRects({
			reference: e,
			floating: t,
			strategy: i
		}) : x.rects), {x: u, y: d} = Wo(l, f, c)), n = -1);
	}
	return {
		x: u,
		y: d,
		placement: f,
		strategy: i,
		middlewareData: m
	};
}, Jo = function(e) {
	return e === void 0 && (e = {}), {
		name: "flip",
		options: e,
		async fn(t) {
			var n;
			let { placement: r, middlewareData: i, rects: a, initialPlacement: o, platform: s, elements: c } = t, { mainAxis: l = !0, crossAxis: u = !0, fallbackPlacements: d, fallbackStrategy: f = "bestFit", fallbackAxisSideDirection: p = "none", flipAlignment: m = !0, ...h } = wo(e, t);
			if ((n = i.arrow) != null && n.alignmentOffset) return {};
			let g = To(r), _ = ko(o), v = To(o) === o, y = await (s.isRTL == null ? void 0 : s.isRTL(c.floating)), b = d || (v || !m ? [Bo(o)] : Mo(o)), x = p !== "none";
			!d && x && b.push(...zo(o, m, p, y));
			let S = [o, ...b], C = await s.detectOverflow(t, h), w = [], T = i.flip?.overflows || [];
			if (l && w.push(C[g]), u) {
				let e = jo(r, a, y);
				w.push(C[e[0]], C[e[1]]);
			}
			if (T = [...T, {
				placement: r,
				overflows: w
			}], !w.every((e) => e <= 0)) {
				let e = (i.flip?.index || 0) + 1, t = S[e];
				if (t && (u !== "alignment" || _ === ko(t) || T.every((e) => ko(e.placement) !== _ || e.overflows[0] > 0))) return {
					data: {
						index: e,
						overflows: T
					},
					reset: { placement: t }
				};
				let n = T.filter((e) => e.overflows[0] <= 0).sort((e, t) => e.overflows[1] - t.overflows[1])[0]?.placement;
				if (!n) switch (f) {
					case "bestFit": {
						let e = T.filter((e) => {
							if (x) {
								let t = ko(e.placement);
								return t === _ || t === "y";
							}
							return !0;
						}).map((e) => [e.placement, e.overflows.filter((e) => e > 0).reduce((e, t) => e + t, 0)]).sort((e, t) => e[1] - t[1])[0]?.[0];
						e && (n = e);
						break;
					}
					case "initialPlacement": n = o;
				}
				if (r !== n) return { reset: { placement: n } };
			}
			return {};
		}
	};
}, Yo = /*#__PURE__*/ new Set(["left", "top"]);
async function Xo(e, t) {
	let { placement: n, platform: r, elements: i } = e, a = await (r.isRTL == null ? void 0 : r.isRTL(i.floating)), o = To(n), s = Eo(n), c = ko(n) === "y", l = Yo.has(o) ? -1 : 1, u = a && c ? -1 : 1, d = wo(t, e), { mainAxis: f, crossAxis: p, alignmentAxis: m } = typeof d == "number" ? {
		mainAxis: d,
		crossAxis: 0,
		alignmentAxis: null
	} : {
		mainAxis: d.mainAxis || 0,
		crossAxis: d.crossAxis || 0,
		alignmentAxis: d.alignmentAxis
	};
	return s && typeof m == "number" && (p = s === "end" ? m * -1 : m), c ? {
		x: p * u,
		y: f * l
	} : {
		x: f * l,
		y: p * u
	};
}
var Zo = function(e) {
	return e === void 0 && (e = 0), {
		name: "offset",
		options: e,
		async fn(t) {
			var n;
			let { x: r, y: i, placement: a, middlewareData: o } = t, s = await Xo(t, e);
			return a === o.offset?.placement && (n = o.arrow) != null && n.alignmentOffset ? {} : {
				x: r + s.x,
				y: i + s.y,
				data: {
					...s,
					placement: a
				}
			};
		}
	};
}, Qo = function(e) {
	return e === void 0 && (e = {}), {
		name: "shift",
		options: e,
		async fn(t) {
			let { x: n, y: r, placement: i, platform: a } = t, { mainAxis: o = !0, crossAxis: s = !1, limiter: c = { fn: (e) => {
				let { x: t, y: n } = e;
				return {
					x: t,
					y: n
				};
			} }, ...l } = wo(e, t), u = {
				x: n,
				y: r
			}, d = await a.detectOverflow(t, l), f = ko(i), p = Do(f), m = u[p], h = u[f], g = (e, t) => Co(t + d[e === "y" ? "top" : "left"], t, t - d[e === "y" ? "bottom" : "right"]);
			o && (m = g(p, m)), s && (h = g(f, h));
			let _ = c.fn({
				...t,
				[p]: m,
				[f]: h
			});
			return {
				..._,
				data: {
					x: _.x - n,
					y: _.y - r,
					enabled: {
						[p]: o,
						[f]: s
					}
				}
			};
		}
	};
};
// node_modules/.pnpm/@floating-ui+utils@0.2.12/node_modules/@floating-ui/utils/dist/floating-ui.utils.dom.mjs
function $o() {
	return typeof window < "u";
}
function es(e) {
	return ns(e) ? (e.nodeName || "").toLowerCase() : "#document";
}
function Q(e) {
	var t;
	return (e == null || (t = e.ownerDocument) == null ? void 0 : t.defaultView) || window;
}
function ts(e) {
	return ((ns(e) ? e.ownerDocument : e.document) || window.document)?.documentElement;
}
function ns(e) {
	return $o() ? e instanceof Node || e instanceof Q(e).Node : !1;
}
function $(e) {
	return $o() ? e instanceof Element || e instanceof Q(e).Element : !1;
}
function rs(e) {
	return $o() ? e instanceof HTMLElement || e instanceof Q(e).HTMLElement : !1;
}
function is(e) {
	return !$o() || typeof ShadowRoot > "u" ? !1 : e instanceof ShadowRoot || e instanceof Q(e).ShadowRoot;
}
function as(e) {
	let { overflow: t, overflowX: n, overflowY: r, display: i } = gs(e);
	return /auto|scroll|overlay|hidden|clip/.test(t + r + n) && i !== "inline" && i !== "contents";
}
function os(e) {
	return /^(table|td|th)$/.test(es(e));
}
function ss(e) {
	try {
		if (e.matches(":popover-open")) return !0;
	} catch {}
	try {
		return e.matches(":modal");
	} catch {
		return !1;
	}
}
var cs = /transform|translate|scale|rotate|perspective|filter/, ls = /paint|layout|strict|content/, us = (e) => !!e && e !== "none", ds;
function fs(e) {
	let t = $(e) ? gs(e) : e;
	return us(t.transform) || us(t.translate) || us(t.scale) || us(t.rotate) || us(t.perspective) || !ms() && (us(t.backdropFilter) || us(t.filter)) || cs.test(t.willChange || "") || ls.test(t.contain || "");
}
function ps(e) {
	let t = vs(e);
	for (; rs(t) && !hs(t);) {
		if (fs(t)) return t;
		if (ss(t)) return null;
		t = vs(t);
	}
	return null;
}
function ms() {
	return ds ??= typeof CSS < "u" && CSS.supports && CSS.supports("-webkit-backdrop-filter", "none"), ds;
}
function hs(e) {
	return /^(html|body|#document)$/.test(es(e));
}
function gs(e) {
	return Q(e).getComputedStyle(e);
}
function _s(e) {
	return $(e) ? {
		scrollLeft: e.scrollLeft,
		scrollTop: e.scrollTop
	} : {
		scrollLeft: e.scrollX,
		scrollTop: e.scrollY
	};
}
function vs(e) {
	if (es(e) === "html") return e;
	let t = e.assignedSlot || e.parentNode || is(e) && e.host || ts(e);
	return is(t) ? t.host : t;
}
function ys(e) {
	let t = vs(e);
	return hs(t) ? (e.ownerDocument || e).body : rs(t) && as(t) ? t : ys(t);
}
function bs(e, t, n) {
	t === void 0 && (t = []), n === void 0 && (n = !0);
	let r = ys(e), i = r === e.ownerDocument?.body, a = Q(r);
	if (i) {
		let e = xs(a);
		return t.concat(a, a.visualViewport || [], as(r) ? r : [], e && n ? bs(e) : []);
	}
	return t.concat(r, bs(r, [], n));
}
function xs(e) {
	return e.parent && Object.getPrototypeOf(e.parent) ? e.frameElement : null;
}
// node_modules/.pnpm/@floating-ui+dom@1.8.0/node_modules/@floating-ui/dom/dist/floating-ui.dom.mjs
function Ss(e) {
	let t = gs(e), n = parseFloat(t.width) || 0, r = parseFloat(t.height) || 0, i = rs(e), a = i ? e.offsetWidth : n, o = i ? e.offsetHeight : r, s = yo(n) !== a || yo(r) !== o;
	return s && (n = a, r = o), {
		width: n,
		height: r,
		$: s
	};
}
function Cs(e) {
	return $(e) ? e : e.contextElement;
}
function ws(e) {
	let t = Cs(e);
	if (!rs(t)) return xo(1);
	let n = t.getBoundingClientRect(), { width: r, height: i, $: a } = Ss(t), o = (a ? yo(n.width) : n.width) / r, s = (a ? yo(n.height) : n.height) / i;
	return o && Number.isFinite(o) || (o = 1), s && Number.isFinite(s) || (s = 1), {
		x: o,
		y: s
	};
}
var Ts = /*#__PURE__*/ xo(0);
function Es(e) {
	let t = Q(e);
	return !ms() || !t.visualViewport ? Ts : {
		x: t.visualViewport.offsetLeft,
		y: t.visualViewport.offsetTop
	};
}
function Ds(e, t, n) {
	return t === void 0 && (t = !1), !!n && t && n === Q(e);
}
function Os(e, t, n, r) {
	t === void 0 && (t = !1), n === void 0 && (n = !1);
	let i = e.getBoundingClientRect(), a = Cs(e), o = xo(1);
	t && (r ? $(r) && (o = ws(r)) : o = ws(e));
	let s = Ds(a, n, r) ? Es(a) : xo(0), c = (i.left + s.x) / o.x, l = (i.top + s.y) / o.y, u = i.width / o.x, d = i.height / o.y;
	if (a && r) {
		let e = Q(a), t = $(r) ? Q(r) : r, n = e, i = xs(n);
		for (; i && t !== n;) {
			let e = ws(i), t = i.getBoundingClientRect(), r = gs(i), a = t.left + (i.clientLeft + parseFloat(r.paddingLeft)) * e.x, o = t.top + (i.clientTop + parseFloat(r.paddingTop)) * e.y;
			c *= e.x, l *= e.y, u *= e.x, d *= e.y, c += a, l += o, n = Q(i), i = xs(n);
		}
	}
	return Uo({
		width: u,
		height: d,
		x: c,
		y: l
	});
}
function ks(e, t) {
	let n = _s(e).scrollLeft;
	return t ? t.left + n : Os(ts(e)).left + n;
}
function As(e, t) {
	let n = e.getBoundingClientRect();
	return {
		x: n.left + t.scrollLeft - ks(e, n),
		y: n.top + t.scrollTop
	};
}
function js(e) {
	let { elements: t, rect: n, offsetParent: r, strategy: i } = e, a = i === "fixed", o = ts(r), s = t ? ss(t.floating) : !1;
	if (r === o || s && a) return n;
	let c = {
		scrollLeft: 0,
		scrollTop: 0
	}, l = xo(1), u = xo(0), d = rs(r);
	if ((d || !a) && ((es(r) !== "body" || as(o)) && (c = _s(r)), d)) {
		let e = Os(r);
		l = ws(r), u.x = e.x + r.clientLeft, u.y = e.y + r.clientTop;
	}
	let f = o && !d && !a ? As(o, c) : xo(0);
	return {
		width: n.width * l.x,
		height: n.height * l.y,
		x: n.x * l.x - c.scrollLeft * l.x + u.x + f.x,
		y: n.y * l.y - c.scrollTop * l.y + u.y + f.y
	};
}
function Ms(e) {
	return e.getClientRects ? Array.from(e.getClientRects()) : [];
}
function Ns(e) {
	let t = _s(e), n = e.ownerDocument.body, r = vo(e.scrollWidth, e.clientWidth, n.scrollWidth, n.clientWidth), i = vo(e.scrollHeight, e.clientHeight, n.scrollHeight, n.clientHeight), a = -t.scrollLeft + ks(e), o = -t.scrollTop;
	return gs(n).direction === "rtl" && (a += vo(e.clientWidth, n.clientWidth) - r), {
		width: r,
		height: i,
		x: a,
		y: o
	};
}
var Ps = 25;
function Fs(e, t, n) {
	n === void 0 && (n = "viewport");
	let r = n === "layoutViewport", i = Q(e), a = ts(e), o = i.visualViewport, s = a.clientWidth, c = a.clientHeight, l = 0, u = 0;
	if (o) {
		let e = !ms() || t === "fixed";
		r ? e || (l = -o.offsetLeft, u = -o.offsetTop) : (s = o.width, c = o.height, e && (l = o.offsetLeft, u = o.offsetTop));
	}
	if (ks(a) <= 0) {
		let e = a.ownerDocument, t = e.body, n = getComputedStyle(t), r = e.compatMode === "CSS1Compat" && parseFloat(n.marginLeft) + parseFloat(n.marginRight) || 0, i = Math.abs(a.clientWidth - t.clientWidth - r), o = getComputedStyle(a).scrollbarGutter === "stable both-edges" ? i / 2 : i;
		o <= Ps && (s -= o);
	}
	return {
		width: s,
		height: c,
		x: l,
		y: u
	};
}
function Is(e, t) {
	let n = Os(e, !0, t === "fixed"), r = n.top + e.clientTop, i = n.left + e.clientLeft, a = ws(e);
	return {
		width: e.clientWidth * a.x,
		height: e.clientHeight * a.y,
		x: i * a.x,
		y: r * a.y
	};
}
function Ls(e, t, n) {
	let r;
	if (t === "viewport" || t === "layoutViewport") r = Fs(e, n, t);
	else if (t === "document") r = Ns(ts(e));
	else if ($(t)) r = Is(t, n);
	else {
		let n = Es(e);
		r = {
			x: t.x - n.x,
			y: t.y - n.y,
			width: t.width,
			height: t.height
		};
	}
	return Uo(r);
}
function Rs(e, t) {
	let n = t.get(e);
	if (n) return n;
	let r = bs(e, [], !1).filter((e) => $(e) && es(e) !== "body"), i = null, a = gs(e).position === "fixed", o = a ? vs(e) : e;
	for (; $(o) && !hs(o);) {
		let e = gs(o), t = fs(o), n = i ? i.position : a ? "fixed" : "";
		!t && (n === "fixed" || n === "absolute" && e.position === "static") ? r = r.filter((e) => e !== o) : i = e, o = vs(o);
	}
	return t.set(e, r), r;
}
function zs(e) {
	let { element: t, boundary: n, rootBoundary: r, strategy: i } = e, a = [...n === "clippingAncestors" ? ss(t) ? [] : Rs(t, this._c) : [].concat(n), r], o = Ls(t, a[0], i), s = o.top, c = o.right, l = o.bottom, u = o.left;
	for (let e = 1; e < a.length; e++) {
		let n = Ls(t, a[e], i);
		s = vo(n.top, s), c = _o(n.right, c), l = _o(n.bottom, l), u = vo(n.left, u);
	}
	return {
		width: c - u,
		height: l - s,
		x: u,
		y: s
	};
}
function Bs(e) {
	let { width: t, height: n } = Ss(e);
	return {
		width: t,
		height: n
	};
}
function Vs(e, t, n) {
	let r = rs(t), i = ts(t), a = n === "fixed", o = Os(e, !0, a, t), s = {
		scrollLeft: 0,
		scrollTop: 0
	}, c = xo(0);
	if ((r || !a) && ((es(t) !== "body" || as(i)) && (s = _s(t)), r)) {
		let e = Os(t, !0, a, t);
		c.x = e.x + t.clientLeft, c.y = e.y + t.clientTop;
	}
	!r && i && (c.x = ks(i));
	let l = i && !r && !a ? As(i, s) : xo(0);
	return {
		x: o.left + s.scrollLeft - c.x - l.x,
		y: o.top + s.scrollTop - c.y - l.y,
		width: o.width,
		height: o.height
	};
}
function Hs(e) {
	return gs(e).position === "static";
}
function Us(e, t) {
	if (!rs(e) || gs(e).position === "fixed") return null;
	if (t) return t(e);
	let n = e.offsetParent;
	return ts(e) === n && (n = n.ownerDocument.body), n;
}
function Ws(e, t) {
	let n = Q(e);
	if (ss(e)) return n;
	if (!rs(e)) {
		let t = vs(e);
		for (; t && !hs(t);) {
			if ($(t) && !Hs(t)) return t;
			t = vs(t);
		}
		return n;
	}
	let r = Us(e, t);
	for (; r && os(r) && Hs(r);) r = Us(r, t);
	return r && hs(r) && Hs(r) && !fs(r) ? n : r || ps(e) || n;
}
var Gs = async function(e) {
	let t = this.getOffsetParent || Ws, n = this.getDimensions, r = await n(e.floating);
	return {
		reference: Vs(e.reference, await t(e.floating), e.strategy),
		floating: {
			x: 0,
			y: 0,
			width: r.width,
			height: r.height
		}
	};
};
function Ks(e) {
	return gs(e).direction === "rtl";
}
var qs = {
	convertOffsetParentRelativeRectToViewportRelativeRect: js,
	getDocumentElement: ts,
	getClippingRect: zs,
	getOffsetParent: Ws,
	getElementRects: Gs,
	getClientRects: Ms,
	getDimensions: Bs,
	getScale: ws,
	isElement: $,
	isRTL: Ks
};
function Js(e, t) {
	return e.x === t.x && e.y === t.y && e.width === t.width && e.height === t.height;
}
function Ys(e, t, n) {
	let r = null, i, a = ts(e);
	function o() {
		var e;
		clearTimeout(i), (e = r) == null || e.disconnect(), r = null;
	}
	function s(n, c) {
		n === void 0 && (n = !1), c === void 0 && (c = 1), o();
		let l = e.getBoundingClientRect(), { left: u, top: d, width: f, height: p } = l;
		if (n || t(), !f || !p) return;
		let m = bo(d), h = bo(a.clientWidth - (u + f)), g = bo(a.clientHeight - (d + p)), _ = bo(u), v = {
			rootMargin: -m + "px " + -h + "px " + -g + "px " + -_ + "px",
			threshold: vo(0, _o(1, c)) || 1
		}, y = !0;
		function b(t) {
			let n = t[0].intersectionRatio;
			if (!Js(l, e.getBoundingClientRect())) return s();
			if (n !== c) {
				if (!y) return s();
				n ? s(!1, n) : i = setTimeout(() => {
					s(!1, 1e-7);
				}, 1e3);
			}
			y = !1;
		}
		try {
			r = new IntersectionObserver(b, {
				...v,
				root: a.ownerDocument
			});
		} catch {
			r = new IntersectionObserver(b, v);
		}
		r.observe(e);
	}
	let c = Q(e), l = () => s(n);
	return c.addEventListener("resize", l), s(!0), () => {
		c.removeEventListener("resize", l), o();
	};
}
function Xs(e, t, n, r) {
	r === void 0 && (r = {});
	let { ancestorScroll: i = !0, ancestorResize: a = !0, elementResize: o = typeof ResizeObserver == "function", layoutShift: s = typeof IntersectionObserver == "function", animationFrame: c = !1 } = r, l = Cs(e), u = i || a ? [...l ? bs(l) : [], ...t ? bs(t) : []] : [];
	u.forEach((e) => {
		i && e.addEventListener("scroll", n), a && e.addEventListener("resize", n);
	});
	let d = l && s ? Ys(l, n, a) : null, f = -1, p = null;
	o && (p = new ResizeObserver((e) => {
		let [r] = e;
		r && r.target === l && p && t && (p.unobserve(t), cancelAnimationFrame(f), f = requestAnimationFrame(() => {
			var e;
			(e = p) == null || e.observe(t);
		})), n();
	}), l && !c && p.observe(l), t && p.observe(t));
	let m, h = c ? Os(e) : null;
	c && g();
	function g() {
		let t = Os(e);
		h && !Js(h, t) && n(), h = t, m = requestAnimationFrame(g);
	}
	return n(), () => {
		var e;
		u.forEach((e) => {
			i && e.removeEventListener("scroll", n), a && e.removeEventListener("resize", n);
		}), d?.(), (e = p) == null || e.disconnect(), p = null, c && cancelAnimationFrame(m);
	};
}
var Zs = Zo, Qs = Qo, $s = Jo, ec = (e, t, n) => {
	let r = /* @__PURE__ */ new Map(), i = n ?? {}, a = {
		...qs,
		...i.platform,
		_c: r
	};
	return qo(e, t, {
		...i,
		platform: a
	});
};
export { Rt as _, Qs as a, J as c, xa as d, sn as f, Rn as g, zn as h, Zs as i, ba as l, hr as m, ec as n, go as o, dr as p, $s as r, fo as s, Xs as t, Xn as u, zt as v };
