import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { api } from "./api";
import { useToast } from "../components/ui";

/** Liste paginée + filtres côté serveur. */
export function useListe(url, filtresInitiaux = {}, options = {}) {
  const [filtres, setFiltres] = useState({ page: 1, ...filtresInitiaux });
  const q = useQuery({ queryKey: [url, filtres], queryFn: () => api.get(url, filtres), placeholderData: (p) => p, ...options });
  const changer = (f) => setFiltres((ancien) => ({ ...ancien, page: 1, ...f }));
  return {
    ...q,
    lignes: q.data?.results ?? (Array.isArray(q.data) ? q.data : []),
    total: q.data?.count ?? 0,
    pages: q.data?.pages ?? 1,
    page: filtres.page,
    filtres,
    changer,
    allerPage: (page) => setFiltres((a) => ({ ...a, page })),
  };
}

export function useDonnees(cle, url, params, options = {}) {
  return useQuery({ queryKey: [cle, params], queryFn: () => api.get(url, params), ...options });
}

/** Mutation qui affiche un toast et rafraîchit les listes concernées. */
export function useAction(fn, { succes, invalider = [], apres } = {}) {
  const client = useQueryClient();
  const toast = useToast();
  return useMutation({
    mutationFn: fn,
    onSuccess: (data, vars) => {
      if (succes) toast.succes(typeof succes === "function" ? succes(data, vars) : succes);
      if (invalider.length) invalider.forEach((k) => client.invalidateQueries({ queryKey: [k] }));
      else client.invalidateQueries();
      apres?.(data, vars);
    },
    onError: (e) => toast.erreur(e.message),
  });
}

export function useInvalider() {
  const client = useQueryClient();
  return (...cles) => (cles.length ? cles.forEach((k) => client.invalidateQueries({ queryKey: [k] })) : client.invalidateQueries());
}
