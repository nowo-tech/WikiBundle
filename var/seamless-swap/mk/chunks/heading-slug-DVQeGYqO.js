// src/Resources/assets/src/plugins/heading-slug.ts
var e = "nowo-h-";
function t(e) {
	let t = [...e.toLowerCase().replace(/[^\p{L}\p{M}\p{N}]+/gu, "-").replace(/^-+|-+$/g, "")].slice(0, 80).join("").replace(/^-+|-+$/g, "");
	return t === "" ? "section" : t;
}
function n(e) {
	let n = /* @__PURE__ */ new Set();
	return e.map((e) => {
		let r = t(e), i = r;
		for (let e = 2; n.has(i); e++) i = `${r}-${e}`;
		return n.add(i), i;
	});
}
export { t as n, n as r, e as t };
