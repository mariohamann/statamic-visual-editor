const n="data-sid-active",f="data-sid-hover",d="data-sid-inner",a="data-sid",l="data-sid-field",m="__sve-bridge-styles",p="sve-mouse-active";function g(t,r){let s="2px",o="currentColor",e="#9CA3AF";try{const i=getComputedStyle(r.top.document.documentElement);s=i.getPropertyValue("--focus-outline-width").trim()||s,o=i.getPropertyValue("--focus-outline-color").trim()||o,e=i.getPropertyValue("--theme-color-gray-400").trim()||e}catch{}t.documentElement.style.setProperty("--sve-outline-width",s),t.documentElement.style.setProperty("--sve-focus-color",o),t.documentElement.style.setProperty("--sve-hover-color",e)}function h(t){if(t.getElementById(m))return;const r=t.createElement("style");r.id=m,r.textContent=`
        [data-sid], [data-sid-field] {
            cursor: pointer;
            outline-width: var(--sve-outline-width, 2px);
            outline-style: dashed;
            outline-color: transparent;
            outline-offset: 2px;
            transition: outline-color 0.15s ease;
        }
        .${p} [data-sid], .${p} [data-sid-field] {
            outline-color: var(--sve-hover-color, #9CA3AF);
        }
        [data-sid-inner],
        [data-sid-hover] {
            outline-width: var(--sve-outline-width, 2px) !important;
            outline-style: dashed !important;
            outline-color: var(--sve-focus-color, currentColor) !important;
            outline-offset: 2px;
        }
        [data-sid-active] {
            outline-width: var(--sve-outline-width, 2px) !important;
            outline-style: solid !important;
            outline-color: var(--sve-focus-color, currentColor) !important;
            outline-offset: 2px;
        }
        [data-sid-inside] {
            outline-offset: -2px;
        }
        [data-sid-inside][data-sid-inner],
        [data-sid-inside][data-sid-hover],
        [data-sid-inside][data-sid-active] {
            outline-offset: -2px !important;
        }
        [data-sid-inside][data-sid-label]::after {
            top: -4px;
        }
        [data-sid][data-sid-label] {
            position: relative;
        }
        [data-sid][data-sid-label]::after {
            /* safe: data-sid-label is populated only by Blade/Antlers auto-escaped output; no XSS risk */
            content: attr(data-sid-label);
            position: absolute;
            top: -8px;
            left: calc(-2px - var(--sve-outline-width, 0));
            transform: translateY(calc(-100%));
            background: var(--sve-focus-color, currentColor);
            color: #fff;
            font-size: 10px;
            font-family: sans-serif;
            padding: 2px 8px !important;
            border-radius: 4px;
            pointer-events: none;
            z-index: 9999;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.15s ease;
        }
        [data-sid-inner][data-sid-label]::after,
        [data-sid-hover][data-sid-label]::after,
        [data-sid-active][data-sid-label]::after {
            opacity: 1;
        }
        .sve-cp-pulse {
            animation: sve-cp-pulse 0.4s ease-out;
        }
        @keyframes sve-cp-pulse {
            0%   { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.5); }
            100% { box-shadow: 0 0 0 8px rgba(59, 130, 246, 0); }
        }
    `,t.head.appendChild(r)}function y(t){let r=t.previousElementSibling;for(;r;){if(r.hasAttribute(a)&&r.getAttribute("data-sid-type")!=="text")return r;const s=r.querySelector(`[${a}]:not([data-sid-type="text"])`);if(s)return s;r=r.previousElementSibling}return null}function v(t,r,s){if(r===null)return s.querySelector(`[${a}="${t}"][data-sid-type="text"]`);const o=s.querySelector(`[${a}="${r}"]`);if(!o)return null;let e=o;for(;e.parentElement&&!e.parentElement.hasAttribute(a)&&!e.nextElementSibling;)e=e.parentElement;let i=e.nextElementSibling;for(;i;){if(i.hasAttribute(a)&&i.getAttribute("data-sid-type")==="text")return i;i=i.nextElementSibling}return null}function E(t){let r=null;return function(o){t.document.documentElement.classList.add(p);const e=t.document.querySelector(`[${d}]`),i=o.target.closest(`[${a}], [${l}]`);e!==i&&(e&&e.removeAttribute(d),i&&i.setAttribute(d,"")),r&&clearTimeout(r),r=setTimeout(()=>{t.document.documentElement.classList.remove(p),t.document.querySelectorAll(`[${d}]`).forEach(c=>{c.removeAttribute(d)})},1500)}}function S(t){return function(s){const o=s.target.closest(`[${a}], [${l}]`);if(!o){t.document.querySelectorAll(`[${n}]`).forEach(i=>{i.removeAttribute(n)});return}if(s.preventDefault(),t.document.querySelectorAll(`[${n}]`).forEach(i=>{i.removeAttribute(n)}),o.setAttribute(n,""),o.hasAttribute(l)){t.top.postMessage({source:"statamic-visual-editor",type:"click",field:o.getAttribute(l),label:o.getAttribute("data-sid-label")||void 0},t.location.origin);return}const e={source:"statamic-visual-editor",type:"click",uid:o.getAttribute(a)};if(o.getAttribute("data-sid-type")==="text"){const i=y(o);e.afterSetUid=i?i.getAttribute(a):null}t.top.postMessage(e,t.location.origin)}}function x(t){let r=null;function s(o){const e=o.target.closest(`[${a}], [${l}]`);if(e&&e.hasAttribute(l)){const u=e.getAttribute(l);if(u===r)return;r=u,t.top.postMessage({source:"statamic-visual-editor",type:"hover",field:u,label:e.getAttribute("data-sid-label")||void 0},t.location.origin);return}const i=e?e.getAttribute(a):null;if(i===r)return;if(r=i,!i){t.top.postMessage({source:"statamic-visual-editor",type:"hover",uid:null},t.location.origin);return}const c={source:"statamic-visual-editor",type:"hover",uid:i};if(e.getAttribute("data-sid-type")==="text"){const u=y(e);c.afterSetUid=u?u.getAttribute(a):null}t.top.postMessage(c,t.location.origin)}return s.reset=()=>{r=null,t.top.postMessage({source:"statamic-visual-editor",type:"hover",uid:null},t.location.origin)},s}function b(t,r){const s=r.querySelector(`[${l}="${t}"]`);if(s)return s;const o=t.replaceAll(".","_");return[...r.querySelectorAll(`[${l}]`)].find(e=>e.getAttribute(l).replaceAll(".","_")===o)||null}function A(t){t.classList.remove("sve-cp-pulse"),t.offsetWidth,t.classList.add("sve-cp-pulse"),setTimeout(()=>t.classList.remove("sve-cp-pulse"),400)}function $(t){return function(s){if(s.source!==t.top)return;const{data:o}=s;if(!(!o||o.source!=="statamic-visual-editor")){if(o.type==="hover"){if(t.document.querySelectorAll(`[${f}]`).forEach(e=>{e.removeAttribute(f)}),o.field){const e=b(o.field,t.document);e&&e.setAttribute(f,"");return}if(o.uid){const e="afterSetUid"in o?v(o.uid,o.afterSetUid,t.document):t.document.querySelector(`[${a}="${o.uid}"]`);e&&e.setAttribute(f,"")}return}if(o.type==="focus"){if(t.document.querySelectorAll(`[${n}]`).forEach(e=>{e.removeAttribute(n)}),o.field){const e=b(o.field,t.document);e&&(e.setAttribute(n,""),e.scrollIntoView({behavior:"smooth",block:"start"}),A(e));return}if(o.uid){const e="afterSetUid"in o?v(o.uid,o.afterSetUid,t.document):t.document.querySelector(`[${a}="${o.uid}"]`);e&&(e.setAttribute(n,""),e.scrollIntoView({behavior:"smooth",block:"start"}),A(e))}}}}}function T(t=window){if(t.self===t.top)return;h(t.document),g(t.document,t),t.document.addEventListener("click",S(t),!0),t.document.addEventListener("mousemove",E(t),!0);const r=x(t);t.document.addEventListener("mouseover",r,!0),t.document.addEventListener("mouseleave",()=>r.reset(),!0),t.addEventListener("message",$(t))}T();
