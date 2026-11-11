// src/Resources/assets/src/diff-nav.ts
var e = "nowoMilkdownDiffBound";
function t(t) {
	if (t.dataset[e]) return !1;
	t.dataset[e] = "1";
	let n = [...t.querySelectorAll(".nowo-md-diff-block")];
	if (!n.length) return !1;
	let r = (e, n) => t.dataset[e] || n, i = document.createElement("div");
	i.className = "nowo-md-diff-nav";
	let a = document.createElement("p");
	a.className = "nowo-md-diff-status", a.setAttribute("role", "status");
	let o = -1, s = (e) => {
		o = o === -1 ? e === 1 ? 0 : n.length - 1 : (o + e + n.length) % n.length;
		let t = n[o];
		t.tabIndex = -1, t.focus(), t.scrollIntoView({ block: "center" }), a.textContent = r("position", "Change {index} of {count}").replace("{index}", String(o + 1)).replace("{count}", String(n.length));
	};
	for (let [e, t, n] of [[
		"previous",
		-1,
		"Previous change"
	], [
		"next",
		1,
		"Next change"
	]]) {
		let a = document.createElement("button");
		a.type = "button", a.className = `nowo-md-diff-${e}`, a.textContent = r(e, n), a.addEventListener("click", () => s(t)), i.append(a);
	}
	return i.append(a), t.querySelector(".nowo-md-diff-summary")?.after(i), t.addEventListener("keydown", (e) => {
		e.ctrlKey || e.metaKey || e.altKey || e.target.closest("input, textarea, select, [contenteditable=\"true\"]") || (e.key === "n" || e.key === "p") && (e.preventDefault(), s(e.key === "n" ? 1 : -1));
	}), !0;
}
function n(e = document) {
	return [...e.querySelectorAll("[data-nowo-milkdown-diff]")].filter(t).length;
}
export { n as bindDiffs };
