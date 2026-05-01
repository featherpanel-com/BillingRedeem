import { ref } from "vue";
import axios from "axios";
import type { AxiosError } from "axios";

export interface RedeemSettings {
  is_enabled: boolean;
  allow_multiple_uses: boolean;
  default_max_uses: number;
}

export interface RedeemCode {
  id: number;
  code: string;
  amount: number;
  reward_type?: "credits" | "billing_plan_trial" | "billing_plan_coupon";
  plan_id?: number | null;
  free_period_days?: number | null;
  discount_percent?: number | null;
  discount_credits?: number | null;
  coupon_scope?: "initial" | "renewal" | "both" | null;
  uses: number;
  max_uses: number;
  expires_at: string | null;
  created_at: string;
  updated_at: string;
  usage_count?: number;
  is_valid?: boolean;
  amount_formatted?: string;
}

export interface RedeemCodeUsage {
  id: number;
  code_id: number;
  user_id: number;
  used_at: string;
  created_at: string;
  updated_at: string;
  email?: string;
  username?: string;
}

export interface RedeemCodesResponse {
  codes: RedeemCode[];
  total: number;
  limit: number;
  offset: number;
}

export interface RedeemCodeUsageResponse {
  usage: RedeemCodeUsage[];
  total: number;
  limit: number;
  offset: number;
}

export interface BillingPlanOption {
  id: number;
  name: string;
  billing_period_days: number;
}

export function useRedeemAdminAPI() {
  const loading = ref(false);
  const error = ref<string | null>(null);

  const handleError = (err: unknown): string => {
    if (axios.isAxiosError(err)) {
      const axiosError = err as AxiosError<{
        message?: string;
        error_message?: string;
        error?: string;
      }>;
      return (
        axiosError.response?.data?.message ||
        axiosError.response?.data?.error_message ||
        axiosError.response?.data?.error ||
        axiosError.message ||
        "An error occurred"
      );
    }
    if (err instanceof Error) {
      return err.message;
    }
    return "An unknown error occurred";
  };

  // Get settings
  const getSettings = async (): Promise<RedeemSettings> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/admin/billingredeem/settings");
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch settings");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Update settings
  const updateSettings = async (
    settings: Partial<RedeemSettings>
  ): Promise<RedeemSettings> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.patch(
        "/api/admin/billingredeem/settings",
        settings
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to update settings");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Get all codes
  const getCodes = async (
    limit: number = 50,
    offset: number = 0
  ): Promise<RedeemCodesResponse> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/admin/billingredeem/codes", {
        params: { limit, offset },
      });
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch codes");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const getPlanOptions = async (): Promise<{ plans: BillingPlanOption[] }> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/admin/billingredeem/plan-options");
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch billing plan options");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Get code by ID
  const getCode = async (id: number): Promise<RedeemCode> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(`/api/admin/billingredeem/codes/${id}`);
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch code");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Create code
  const createCode = async (
    codeData: Partial<RedeemCode>
  ): Promise<RedeemCode> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post(
        "/api/admin/billingredeem/codes",
        codeData
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to create code");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Update code
  const updateCode = async (
    id: number,
    codeData: Partial<RedeemCode>
  ): Promise<RedeemCode> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.patch(
        `/api/admin/billingredeem/codes/${id}`,
        codeData
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to update code");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Delete code
  const deleteCode = async (id: number): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.delete(
        `/api/admin/billingredeem/codes/${id}`
      );
      if (!response.data || !response.data.success) {
        throw new Error(response.data?.message || "Failed to delete code");
      }
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Get code usage
  const getCodeUsage = async (
    id: number,
    limit: number = 50,
    offset: number = 0
  ): Promise<RedeemCodeUsageResponse> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(
        `/api/admin/billingredeem/codes/${id}/usage`,
        {
          params: { limit, offset },
        }
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch code usage");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  return {
    loading,
    error,
    getSettings,
    updateSettings,
    getCodes,
    getPlanOptions,
    getCode,
    createCode,
    updateCode,
    deleteCode,
    getCodeUsage,
  };
}
