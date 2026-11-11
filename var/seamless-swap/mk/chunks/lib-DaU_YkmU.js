import { $t as e, A as t, C as n, E as r, Et as i, Ot as a, S as o, T as s, _ as c, a as l, an as u, b as d, it as f, k as p, l as m, m as h, o as g, on as _, p as v, s as y, t as b, u as x, v as S, w as C, x as w, y as T } from "./lib-9QpRoHAM.js";
// node_modules/.pnpm/nanoid@6.0.2/node_modules/nanoid/index.browser.js
var E = (e) => crypto.getRandomValues(new Uint8Array(e)), D = (e, t, n) => {
	let r = 256 - 256 % e.length;
	if (r === 256) {
		let r = e.length - 1;
		return (i = t) => {
			if (!i) return "";
			let a = "";
			for (;;) {
				let t = n(i), o = i |= 0;
				for (; o--;) if (a += e[t[o] & r], a.length >= i) return a;
			}
		};
	}
	return (i = t) => {
		if (!i) return "";
		let a = "", o = Math.ceil(409.6 * i / r);
		for (;;) {
			let t = n(o), s = o;
			for (; s--;) if (t[s] < r && (a += e[t[s] % e.length], a.length >= i)) return a;
		}
	};
}, O = (e, t = 21) => D(e, t | 0, E);
O("abcedfghicklmn", 10);
function k(e, t, n) {
	let r = e.findIndex(([e]) => e === t);
	if (r === -1) return [...e, n];
	let i = e.slice();
	return i[r] = n, i;
}
function A(e, t) {
	let n = x(e), r = (i) => async () => {
		r.key = n, await i.wait(b);
		let a = t(i);
		return i.get(m).create(n, a), r.run = (t) => i.get(m).call(e, t), () => {
			i.get(m).remove(n);
		};
	};
	return r;
}
function j(e) {
	let t = (n) => async () => {
		await n.wait(y);
		let r = e(n);
		return n.update(c, (e) => [...e, r]), t.inputRule = r, () => {
			n.update(c, (e) => e.filter((e) => e !== r));
		};
	};
	return t;
}
function M(e) {
	let t = (n) => async () => {
		await n.wait(y);
		let r = e(n);
		return n.update(C, (e) => [...e, r]), t.pasteRule = r, () => {
			n.update(C, (e) => e.filter((e) => e !== r));
		};
	};
	return t;
}
function N(e, t) {
	let n = (r) => async () => {
		let i = t(r);
		return r.update(d, (t) => k(t, e, [e, i])), n.id = e, n.schema = i, () => {
			r.update(d, (t) => t.filter(([t]) => t !== e));
		};
	};
	return n.type = (t) => {
		let n = t.get(p).marks[e];
		if (!n) throw u(e);
		return n;
	}, n;
}
function P(e, t) {
	let n = (r) => async () => {
		let i = t(r);
		return r.update(o, (t) => k(t, e, [e, i])), n.id = e, n.schema = i, () => {
			r.update(o, (t) => t.filter(([t]) => t !== e));
		};
	};
	return n.type = (t) => {
		let n = t.get(p).nodes[e];
		if (!n) throw _(e);
		return n;
	}, n;
}
function F(e) {
	let t, n = (n) => async () => (await n.wait(y), t = e(n), n.update(s, (e) => [...e, t]), () => {
		n.update(s, (e) => e.filter((e) => e !== t));
	});
	return n.plugin = () => t, n.key = () => t.spec.key, n;
}
function I(e) {
	let t = (n) => async () => {
		await n.wait(g);
		let r = n.get(S), i = e(n), a = r.addObjectKeymap(i);
		return t.keymap = i, () => {
			a();
		};
	};
	return t;
}
function L(e, t) {
	let n = (r) => async () => {
		await r.wait(y);
		let a = t(r);
		return e.type(r) instanceof i ? r.update(w, (t) => [...t, [e.id, a]]) : r.update(T, (t) => [...t, [e.id, a]]), n.view = a, n.type = e, () => {
			e.type(r) instanceof i ? r.update(w, (t) => t.filter((t) => t[0] !== e.id)) : r.update(T, (t) => t.filter((t) => t[0] !== e.id));
		};
	};
	return n;
}
function R(t, n) {
	let r = e(t, n), i = (e) => (e.inject(r), () => () => {
		e.remove(r);
	});
	return i.key = r, i;
}
function z(e, t) {
	let n = R(t, e), r = P(e, (e) => e.get(n.key)(e)), i = [n, r];
	return i.id = r.id, i.node = r, i.type = (e) => r.type(e), i.ctx = n, i.key = n.key, i.extendSchema = (n) => z(e, n(t)), i;
}
function B(e, t) {
	let n = R(t, e), r = N(e, (e) => e.get(n.key)(e)), i = [n, r];
	return i.id = r.id, i.mark = r, i.type = (e) => r.type(e), i.ctx = n, i.key = n.key, i.extendSchema = (n) => B(e, n(t)), i;
}
function V(e, t) {
	let n = R(Object.fromEntries(Object.entries(t).map(([e, { shortcuts: t, priority: n }]) => [e, {
		shortcuts: t,
		priority: n
	}])), `${e}Keymap`), r = I((e) => {
		let r = e.get(n.key), i = Object.entries(t).flatMap(([e, { command: t }]) => {
			let n = r[e], i = [n.shortcuts].flat(), a = n.priority;
			return i.map((e) => [e, {
				key: e,
				onRun: t,
				priority: a
			}]);
		});
		return Object.fromEntries(i);
	}), i = [n, r];
	return i.ctx = n, i.shortcuts = r, i.key = n.key, i.keymap = r.keymap, i;
}
var H = (e, t = () => ({})) => R(t, `${e}Attr`), U = (e, t = () => ({})) => R(t, `${e}Attr`);
function W(e, t, n) {
	let i = R(n ?? {}, e), a = (e) => async () => {
		await e.wait(l);
		let n = {
			plugin: t(e),
			options: e.get(i.key)
		};
		return e.update(r, (e) => [...e, n]), () => {
			e.update(r, (e) => e.filter((e) => e !== n));
		};
	}, o = [i, a];
	return o.id = e, o.plugin = a, o.options = i, o;
}
function G(e) {
	return (n) => {
		let r = n.get(h), i = n.get(p), a = n.get(t);
		if (!e) return a(r.state.doc);
		let o = r.state.doc.slice(e.from, e.to, !0), s = i.topNodeType.createAndFill(null, o.content);
		return s ? a(s) : (console.error("No document found"), "");
	};
}
function K(e, t = !1) {
	return (r) => {
		let i = r.get(h), o = r.get(n)(e);
		if (!o) return;
		if (!t) {
			let { state: e } = i;
			return i.dispatch(e.tr.replace(0, e.doc.content.size, new a(o.content, 0, 0)));
		}
		let c = r.get(p), l = r.get(v)({
			schema: c,
			doc: o,
			plugins: r.get(s)
		}), u = f.create(l);
		i.updateState(u);
	};
}
export { O as _, B as a, z as c, W as d, I as f, K as g, G as h, U as i, M as l, L as m, R as n, P as o, V as p, j as r, H as s, A as t, F as u };
