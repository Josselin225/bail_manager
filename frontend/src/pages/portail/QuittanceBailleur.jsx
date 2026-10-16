import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import { Imprimable } from "../../components/Document";
import { CorpsQuittance } from "../impression/Quittance";

export default function QuittanceBailleur() {
  const { id } = useParams();
  const { data: q, isLoading, error } = useQuery({ queryKey: ["quittance-bailleur", id], queryFn: () => api.get(`/portail-bailleur/quittances/${id}/`) });
  return (
    <Imprimable titre={q ? `Quittance ${q.reference_recu}` : "Quittance"} chargement={isLoading} erreur={error}>
      {q && <CorpsQuittance q={q} e={q.entreprise} souche={false} />}
    </Imprimable>
  );
}
