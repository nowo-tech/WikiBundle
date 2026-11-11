function e(e, n = (...e) => fetch(...e)) {
	let r = /* @__PURE__ */ new Map(), i = /* @__PURE__ */ new Map(), a = !1, o = (e) => ({
		id: e,
		title: "",
		url: "",
		visible: !1
	});
	async function s() {
		a = !1;
		let r = i;
		i = /* @__PURE__ */ new Map();
		let s = [...r.keys()];
		for (let i = 0; i < s.length; i += 50) {
			let a = s.slice(i, i + 50), c = [];
			try {
				let t = await n(`${e}${e.includes("?") ? "&" : "?"}ids=${encodeURIComponent(a.join(","))}`, {
					credentials: "same-origin",
					headers: { Accept: "application/json" }
				});
				t.ok && (c = await t.json());
			} catch {
				c = [];
			}
			for (let e of a) {
				let n = Array.isArray(c) ? c.find((t) => t && t.id === e) : void 0;
				r.get(e)(n && n.visible === !0 && typeof n.title == "string" ? {
					id: e,
					title: n.title,
					url: t(n.url),
					visible: !0
				} : o(e));
			}
		}
	}
	return {
		resolve(t) {
			let n = r.get(t);
			return !n && !e ? Promise.resolve(o(t)) : (n || (n = new Promise((e) => i.set(t, e)), r.set(t, n), a || (a = !0, queueMicrotask(() => void s()))), n);
		},
		remember(e) {
			r.set(e.id, Promise.resolve({
				...e,
				url: t(e.url)
			}));
		}
	};
}
function t(e) {
	if (typeof e != "string") return "";
	let t = e.replace(/[\t\n\r]/g, "").replace(/^[\u0000-\u0020]+|[\u0000-\u0020]+$/g, "");
	if (t === "" || /^[\\/][\\/]/.test(t)) return "";
	if (/^(?:\/|\.{1,2}\/|#|\?)/.test(t)) return t;
	try {
		let e = new URL(t);
		return e.protocol === "https:" || e.protocol === "http:" ? t : "";
	} catch {
		return /^[^:]*$/.test(t) ? t : "";
	}
}
export { t as n, e as t };
