import { a as e } from "./runtime-DBQRQGPT.js";
// src/Resources/assets/src/version.ts
function t(t, n, r) {
	let i = document.createElement("div");
	i.className = "nowo-milkdown__version", i.setAttribute("role", "region"), i.setAttribute("aria-label", e(r, "version.region"));
	let a = document.createElement("p");
	a.className = "nowo-milkdown__version-text", a.textContent = n.label ? e(r, "version.viewing", { label: n.label }) : e(r, "version.viewing_old"), i.append(a);
	let o = (n) => {
		let a = document.createElement("button");
		a.type = "button", a.className = `nowo-milkdown__action nowo-milkdown__version-${n}`, a.textContent = e(r, `version.${n}`), a.addEventListener("click", () => t.dispatchEvent(new CustomEvent(`nowo-milkdown:version-${n}`, {
			bubbles: !0,
			detail: {}
		}))), i.append(a);
	};
	return n.restore && o("restore"), n.copy && o("copy"), i;
}
export { t as versionBanner };
