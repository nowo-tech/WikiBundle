import { m as e } from "./lib-9QpRoHAM.js";
import { u as t } from "./lib-DaU_YkmU.js";
import { a as n, c as r, d as i, f as a, g as o, h as s, i as c, l, m as u, n as d, p as f, r as p, s as m, u as h, v as g } from "./plugin-Cnor8ZP7.js";
// src/Resources/assets/src/comments/index.ts
function _(t, n) {
	return t.action((t) => {
		let i = t.get(e), a = n.getBoundingClientRect().top, o = /* @__PURE__ */ new Map();
		for (let { id: e, range: t } of r(i.state)) try {
			o.set(e, Math.max(0, i.coordsAtPos(t.from).top - a));
		} catch {
			o.set(e, 0);
		}
		return o;
	});
}
function v(e, n, r) {
	let [i, a] = p(n, {
		create: (e) => r()?.create(e),
		open: (e) => r()?.open(e)
	});
	e.use(t(() => i)).use(t(() => a));
}
function y(t, p, g, _ = () => !0) {
	let v = (n, r) => _() ? t.action((t) => r(t.get(e))) : n, y = (e) => d.getState(e.state)?.threads ?? /* @__PURE__ */ new Map();
	return {
		set: (e) => v(void 0, (t) => {
			f(t, { threads: h(e) }), c(t)?.resolveNow();
		}),
		upsert: (e) => v(void 0, (t) => {
			let n = l(e);
			if (!n) return;
			let r = new Map(y(t));
			r.set(n.id, n), f(t, { threads: r }), c(t)?.resolveNow();
		}),
		remove: (e) => v(void 0, (t) => {
			let n = new Map(y(t));
			n.delete(e) && f(t, { threads: n });
		}),
		status: (e) => v("unknown", (t) => c(t)?.status(e) ?? "unknown"),
		selectionAnchor: () => v(null, (e) => p.canCreate ? i(e.state, e.state.selection.from, e.state.selection.to, p.yjs()) : null),
		start: () => v(void 0, (e) => {
			let t = u(e, p);
			t && !t.cancelled && g()?.create(t);
		}),
		focus: (e) => v(!1, (t) => m(t, p, e)),
		next: () => v(!1, (e) => s(e, p, 1)),
		previous: () => v(!1, (e) => s(e, p, -1)),
		showResolved: (e) => v(void 0, (t) => f(t, { showResolved: e })),
		snapshot: () => v([], (e) => {
			let t = new o(e.state.doc), n = p.yjs();
			return r(e.state).flatMap(({ id: r, range: a }) => {
				let o = i(e.state, a.from, a.to, n, t);
				return o ? [{
					id: r,
					anchor: o
				}] : [];
			});
		}),
		refresh: async (e) => {
			_() && await g()?.refresh(e);
		},
		open: (e) => {
			_() && g()?.open(e);
		},
		reveal: (e) => v(!1, (t) => a(t, e)),
		count: () => v({
			open: 0,
			resolved: 0,
			orphaned: 0,
			page: 0,
			total: 0
		}, (e) => n(e.state)),
		mount: (e) => {
			let t = _() ? g() : null;
			return t ? t.mount(e) : !1;
		}
	};
}
export { y as commentsApi, g as parseCommentAnchor, _ as threadTops, v as useComments };
