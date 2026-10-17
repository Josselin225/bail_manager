import { forwardRef, useEffect, useImperativeHandle, useRef, useState } from "react";
import { Eraser } from "lucide-react";

/**
 * Zone de signature au doigt ou au stylet (tablette, téléphone, souris).
 * `ref.current.image()` renvoie le PNG (data URL), ou null si rien n'a été tracé.
 */
const Signature = forwardRef(function Signature({ libelle, nom }, ref) {
  const canvas = useRef(null);
  const trace = useRef(false);
  const [vide, setVide] = useState(true);

  const preparer = () => {
    const c = canvas.current;
    const r = c.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;
    c.width = Math.round(r.width * ratio);
    c.height = Math.round(r.height * ratio);
    const ctx = c.getContext("2d");
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 2.4;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    ctx.strokeStyle = "#0e2a47";
    setVide(true);
  };
  useEffect(() => {
    preparer();
    window.addEventListener("resize", preparer);
    return () => window.removeEventListener("resize", preparer);
  }, []);

  const point = (e) => {
    const r = canvas.current.getBoundingClientRect();
    return [e.clientX - r.left, e.clientY - r.top];
  };
  const debut = (e) => {
    e.preventDefault();
    canvas.current.setPointerCapture(e.pointerId);
    trace.current = true;
    const ctx = canvas.current.getContext("2d");
    const [x, y] = point(e);
    ctx.beginPath();
    ctx.moveTo(x, y);
    ctx.lineTo(x + 0.1, y + 0.1);
    ctx.stroke();
    setVide(false);
  };
  const deplacer = (e) => {
    if (!trace.current) return;
    const ctx = canvas.current.getContext("2d");
    const [x, y] = point(e);
    ctx.lineTo(x, y);
    ctx.stroke();
  };
  const fin = () => { trace.current = false; };

  useImperativeHandle(ref, () => ({
    image: () => (vide ? null : canvas.current.toDataURL("image/png")),
    effacer: preparer,
  }), [vide]);

  return (
    <div className="zone-signature">
      <div className="ligne-flex" style={{ justifyContent: "space-between" }}>
        <span><b>{libelle}</b>{nom && <span className="discret"> — {nom}</span>}</span>
        <button type="button" className="btn petit fantome" onClick={preparer} disabled={vide}><Eraser size={14} /> Effacer</button>
      </div>
      <canvas ref={canvas} className={vide ? "vide" : ""} aria-label={`Signature : ${libelle}`}
        onPointerDown={debut} onPointerMove={deplacer} onPointerUp={fin} onPointerCancel={fin} onPointerLeave={fin} />
      {vide && <span className="consigne-signature">Signez ici avec le doigt</span>}
    </div>
  );
});

export default Signature;
