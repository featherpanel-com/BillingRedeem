import { ref } from "vue";
import axios from "axios";
import type { AxiosError } from "axios";

export interface RedeemResponse {
  code: string;
  amount: number;
  reward_type?: "credits" | "billing_plan_trial";
  plan_id?: number | null;
  free_period_days?: number | null;
  subscription?: Record<string, unknown> | null;
  amount_formatted: string;
  new_credits: number;
  new_credits_formatted: string;
}

export interface RedeemHistoryItem {
  id: number;
  code_id: number;
  user_id: number;
  used_at: string;
  created_at: string;
  updated_at: string;
  code?: string;
  amount?: number;
}

export interface RedeemHistory {
  history: RedeemHistoryItem[];
  total: number;
  limit: number;
  offset: number;
}

export function useRedeemAPI() {
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

  // Redeem a code
  const redeem = async (code: string): Promise<RedeemResponse> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post("/api/user/billingredeem/redeem", {
        code: code.trim(),
      });
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to redeem code");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Get redemption history
  const getHistory = async (
    limit: number = 50,
    offset: number = 0
  ): Promise<RedeemHistory> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/user/billingredeem/history", {
        params: { limit, offset },
      });
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch redemption history");
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
    redeem,
    getHistory,
  };
}
