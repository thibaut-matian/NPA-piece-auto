import { createAsyncThunk } from "@reduxjs/toolkit";
import { setSite } from "../slices/siteSlice";

export const fetchSiteThunk = createAsyncThunk(
  "site/fetchSite",
  async (_, thunkAPI) => {
    try {
      //const token = thunkAPI.getState().user?.token;

      const response = await fetch(`${import.meta.env.VITE_API_URL}/wp-json/`, {
        "Content-Type": "application/json",
        //...(token && { Authorization: `Bearer ${token}` }),
      });

      if (!response.ok) {
        throw new Error("Impossible de récupérer les informations du site.");
      }

      let siteData = await response.json();
      let logoUrl = null;
      const contentResponse = await fetch(
        `${import.meta.env.VITE_API_URL}/wp-json/custom/v1/site-content`,
      );
      if (!contentResponse.ok) {
        throw new Error("Impossible de récupérer les contenus ACF du site.");
      }
      const contentData = await contentResponse.json();
      if (!contentData || typeof contentData.fields !== "object" || contentData.fields === null) {
        throw new Error("La réponse des contenus ACF du site est invalide.");
      }

      try {
        if (siteData.site_logo) {
          const mediaResponse = await fetch(
            `${import.meta.env.VITE_API_URL}/wp-json/wp/v2/media/${siteData.site_logo}`,
          );
          if (mediaResponse.ok) {
            const mediaData = await mediaResponse.json();
            logoUrl = mediaData.source_url ?? null;
          }
        }
      } catch {}

      // const formattedData = {
      //   name: siteData.name,
      //   description: siteData.description,
      //   url: siteData.url || siteData.home,
      //   logoUrl,
      //   faviconUrl: siteData.site_icon_url ?? null,
      // };

      siteData.logoUrl = logoUrl ?? null;
      siteData.faviconUrl = siteData.site_icon_url ?? null;
      siteData.contentFields = contentData.fields;

      thunkAPI.dispatch(setSite(siteData));

      return siteData;
    } catch (error) {
      return thunkAPI.rejectWithValue(error.message);
    }
  },
);
