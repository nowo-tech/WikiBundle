// src/Resources/assets/src/emoji.ts
var e = [
	["smile", "😄"],
	["grin", "😁"],
	["joy", "😂"],
	["wink", "😉"],
	["thinking", "🤔"],
	["heart", "❤️"],
	["thumbs-up", "👍"],
	["thumbs-down", "👎"],
	["clap", "👏"],
	["tada", "🎉"],
	["rocket", "🚀"],
	["fire", "🔥"],
	["star", "⭐"],
	["sparkles", "✨"],
	["check", "✅"],
	["cross", "❌"],
	["warning", "⚠️"],
	["bug", "🐛"],
	["bulb", "💡"],
	["memo", "📝"],
	["books", "📚"],
	["pushpin", "📌"],
	["link", "🔗"],
	["lock", "🔒"],
	["key", "🔑"],
	["gear", "⚙️"],
	["wrench", "🔧"],
	["hammer", "🔨"],
	["chart", "📈"],
	["calendar", "📅"],
	["clock", "⏰"],
	["eyes", "👀"],
	["wave", "👋"],
	["pray", "🙏"],
	["coffee", "☕"],
	["package", "📦"],
	["construction", "🚧"],
	["stop", "🛑"],
	["question", "❓"],
	["info", "ℹ️"]
], t = (e) => e.toLowerCase().split(/[\s_-]+/).filter(Boolean);
function n(e, t, n) {
	let { name: r } = e;
	if (r === t) return 0;
	if (r.startsWith(t)) return 1;
	if (r.includes(`-${t}`)) return 2;
	if (r.includes(t)) return 3;
	let i = `${r.replace(/-/g, " ")} ${e.keywords}`;
	return n.every((e) => i.includes(e)) ? n.every((e) => ` ${i}`.includes(` ${e}`)) ? 4 : 5 : -1;
}
function r(e, r, i) {
	let a = t(r);
	if (!a.length) return {
		names: e.slice(0, i).map((e) => e.name),
		total: e.length
	};
	let o = a.join("-"), s = [];
	return e.forEach((e, t) => {
		let r = n(e, o, a);
		r >= 0 && s.push({
			name: e.name,
			score: r,
			index: t
		});
	}), s.sort((e, t) => e.score - t.score || e.index - t.index), {
		names: s.slice(0, i).map((e) => e.name),
		total: s.length
	};
}
function i(e, t) {
	let n = new Map(t.map((e) => [e.name, e])), r = [], i = /* @__PURE__ */ new Set();
	for (let t of e) i.has(t) || (i.add(t), r.push(n.get(t) ?? {
		name: t,
		keywords: ""
	}));
	for (let e of t) i.has(e.name) || r.push(e);
	return r;
}
export { r as n, e as r, i as t };
