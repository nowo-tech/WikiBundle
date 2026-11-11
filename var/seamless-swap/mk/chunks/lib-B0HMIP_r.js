import { ot as e, q as t, st as n, ut as r } from "./lib-9QpRoHAM.js";
import { n as i, u as a } from "./lib-DaU_YkmU.js";
import { t as o } from "./purify.es-CZ6K1J3y.js";
import { a as s, d as c, i as l, n as u, o as d, r as f, t as p } from "./floating-ui.dom-DE0KslHM.js";
import { t as m } from "./isObject-tm8H7DiP.js";
import { n as h, t as g } from "./now-BV13JFoZ.js";
// node_modules/.pnpm/lodash-es@4.18.1/node_modules/lodash-es/debounce.js
var _ = "Expected a function", v = Math.max, y = Math.min;
function b(e, t, n) {
	var r, i, a, o, s, c, l = 0, u = !1, d = !1, f = !0;
	if (typeof e != "function") throw TypeError(_);
	t = h(t) || 0, m(n) && (u = !!n.leading, d = "maxWait" in n, a = d ? v(h(n.maxWait) || 0, t) : a, f = "trailing" in n ? !!n.trailing : f);
	function p(t) {
		var n = r, a = i;
		return r = i = void 0, l = t, o = e.apply(a, n), o;
	}
	function b(e) {
		return l = e, s = setTimeout(C, t), u ? p(e) : o;
	}
	function x(e) {
		var n = e - c, r = e - l, i = t - n;
		return d ? y(i, a - r) : i;
	}
	function S(e) {
		var n = e - c, r = e - l;
		return c === void 0 || n >= t || n < 0 || d && r >= a;
	}
	function C() {
		var e = g();
		if (S(e)) return w(e);
		s = setTimeout(C, x(e));
	}
	function w(e) {
		return s = void 0, f && r ? p(e) : (r = i = void 0, o);
	}
	function T() {
		s !== void 0 && clearTimeout(s), l = 0, r = c = i = s = void 0;
	}
	function E() {
		return s === void 0 ? o : w(g());
	}
	function D() {
		var e = g(), n = S(e);
		if (r = arguments, i = this, c = e, n) {
			if (s === void 0) return b(c);
			if (d) return clearTimeout(s), s = setTimeout(C, t), p(c);
		}
		return s === void 0 && (s = setTimeout(C, t)), o;
	}
	return D.cancel = T, D.flush = E, D;
}
// node_modules/.pnpm/lodash-es@4.18.1/node_modules/lodash-es/throttle.js
var x = "Expected a function";
function S(e, t, n) {
	var r = !0, i = !0;
	if (typeof e != "function") throw TypeError(x);
	return m(n) && (r = "leading" in n ? !!n.leading : r, i = "trailing" in n ? !!n.trailing : i), b(e, t, {
		leading: r,
		maxWait: t,
		trailing: i
	});
}
// node_modules/.pnpm/@milkdown+plugin-tooltip@7.23.0/node_modules/@milkdown/plugin-tooltip/lib/index.js
var C = class {
	#e;
	#t;
	#n;
	#r;
	#i;
	#a;
	#o;
	#s;
	#c;
	#l;
	#u;
	constructor(e) {
		this.#a = !1, this.onShow = () => {}, this.onHide = () => {}, this.#d = (e) => {
			u(e, this.element, {
				placement: this.#r.placement ?? "top",
				middleware: [
					f(),
					l(this.#c),
					s(this.#l),
					...this.#n
				],
				...this.#r
			}).then(({ x: e, y: t }) => {
				Object.assign(this.element.style, {
					left: `${e}px`,
					top: `${t}px`
				});
			}).catch(console.error);
		}, this.#f = (e) => this.#i !== e.dom.parentElement, this.#p = (e, n) => {
			let { state: r, composing: i } = e, { selection: a, doc: o } = r, { ranges: s } = a, c = Math.min(...s.map((e) => e.$from.pos)), l = Math.max(...s.map((e) => e.$to.pos)), u = (e) => !!e && e.doc.eq(o) && e.selection.eq(a), d = u(n) && u(this.#s);
			if (this.#a ||= ((this.#i ?? e.dom.parentElement ?? document.body).appendChild(this.element), !0), i || d) return;
			if (this.#s = r, this.#o?.(), this.#o = void 0, !this.#t(e, n)) {
				this.hide();
				return;
			}
			let f = {
				getBoundingClientRect: () => t(e, c, l),
				contextElement: e.dom
			};
			this.#f(e) ? this.#o = p(f, this.element, () => this.#d(f)) : this.#d(f), this.show();
		}, this.update = (e, t) => {
			this.#u(e, t);
		}, this.#m = (e) => {
			let { doc: t, selection: n } = e.state, { empty: i, from: a, to: o } = n, s = !t.textBetween(a, o).length && e.state.selection instanceof r, c = this.element.contains(document.activeElement), l = !e.hasFocus() && !c, u = !e.editable;
			return !(l || i || s || u);
		}, this.destroy = () => {
			this.#o?.(), this.#u.cancel();
		}, this.show = (e, t) => {
			if (this.element.dataset.show = "true", e) {
				this.#o?.(), this.#o = void 0;
				let n = {
					...e,
					contextElement: t?.dom
				};
				t && this.#f(t) ? this.#o = p(n, this.element, () => this.#d(n)) : this.#d(n);
			}
			this.onShow();
		}, this.hide = () => {
			this.element.dataset.show !== "false" && (this.element.dataset.show = "false", this.onHide());
		}, this.element = e.content, this.#e = e.debounce ?? 200, this.#t = e.shouldShow ?? this.#m, this.#c = e.offset, this.#l = e.shift, this.#n = e.middleware ?? [], this.#r = e.floatingUIOptions ?? {}, this.#i = e.root, this.element.dataset.show = "false", this.#u = S(this.#p, this.#e);
	}
	#d;
	#f;
	#p;
	#m;
};
function w(t) {
	let r = i({}, `${t}_TOOLTIP_SPEC`), o = a((i) => {
		let a = i.get(r.key);
		return new e({
			key: new n(`${t}_TOOLTIP`),
			...a
		});
	}), s = [r, o];
	return s.key = r.key, s.pluginKey = o.key, r.meta = {
		package: "@milkdown/plugin-tooltip",
		displayName: `Ctx<tooltipSpec>|${t}`
	}, o.meta = {
		package: "@milkdown/plugin-tooltip",
		displayName: `Prose<tooltip>|${t}`
	}, s;
}
// node_modules/.pnpm/@milkdown+components@7.23.0_@codemirror+language@6.13.1_@codemirror+state@6.7.6_@codemi_2e95d647a0d9839042dd5c8a016897e3/node_modules/@milkdown/components/lib/index.js
function T({ icon: e, class: t, onClick: n }) {
	return /* @__PURE__ */ c("span", {
		class: d("milkdown-icon", t),
		onPointerdown: n,
		innerHTML: e ? o.sanitize(e.trim()) : void 0
	});
}
T.props = {
	icon: {
		type: String,
		required: !1
	},
	class: {
		type: String,
		required: !1
	},
	onClick: {
		type: Function,
		required: !1
	}
};
export { b as a, S as i, C as n, w as r, T as t };
