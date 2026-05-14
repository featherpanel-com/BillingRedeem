<script setup lang="ts">
import { ref, onMounted, watch } from "vue";
import { Card } from "@/components/ui/card";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import {
  Loader2,
  Settings,
  Gift,
  Save,
  Plus,
  Edit,
  Trash2,
  ChevronLeft,
  ChevronRight,
  Users,
  X,
} from "lucide-vue-next";
import {
  useRedeemAdminAPI,
  type RedeemSettings,
  type RedeemCode,
  type RedeemCodeUsage,
  type BillingPlanOption,
} from "@/composables/useRedeemAdminAPI";
import { useToast } from "vue-toastification";

const toast = useToast();
const {
  getSettings,
  updateSettings,
  getCodes,
  createCode,
  updateCode,
  deleteCode,
  getCodeUsage,
  getPlanOptions,
  loading,
} = useRedeemAdminAPI();

// Settings
const settings = ref<RedeemSettings | null>(null);
const savingSettings = ref(false);

// Codes
const codes = ref<RedeemCode[]>([]);
const codesPage = ref(1);
const codesTotal = ref(0);
const loadingCodes = ref(false);
const billingPlanOptions = ref<BillingPlanOption[]>([]);

// Code form
const showCodeForm = ref(false);
const editingCode = ref<RedeemCode | null>(null);
const codeForm = ref({
  code: "",
  amount: 0,
  reward_type: "credits" as "credits" | "billing_plan_trial" | "billing_plan_coupon",
  plan_id: null as number | null,
  free_period_days: 30,
  discount_percent: 0,
  discount_credits: 0,
  coupon_scope: "initial" as "initial" | "renewal" | "both",
  max_uses: 1,
  expires_at: "",
});

// Code usage
const selectedCode = ref<RedeemCode | null>(null);
const codeUsage = ref<RedeemCodeUsage[]>([]);
const usagePage = ref(1);
const usageTotal = ref(0);
const loadingUsage = ref(false);
const showUsage = ref(false);

// Active tab
const activeTab = ref("codes");

// Watch for tab changes
watch(activeTab, (newTab) => {
  if (newTab === "settings" && !settings.value) {
    loadSettings();
  } else if (newTab === "codes" && codes.value.length === 0) {
    loadCodes();
  }
});

const loadSettings = async () => {
  try {
    settings.value = await getSettings();
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load settings");
  }
};

const saveSettings = async () => {
  if (!settings.value) return;

  savingSettings.value = true;
  try {
    settings.value = await updateSettings(settings.value);
    toast.success("Settings saved successfully!");
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to save settings");
  } finally {
    savingSettings.value = false;
  }
};

const loadCodes = async (page: number = 1) => {
  codesPage.value = page;
  loadingCodes.value = true;
  try {
    const result = await getCodes(20, (page - 1) * 20);
    codes.value = result.codes;
    codesTotal.value = result.total;
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load codes");
  } finally {
    loadingCodes.value = false;
  }
};

const loadBillingPlanOptions = async () => {
  try {
    const result = await getPlanOptions();
    billingPlanOptions.value = result.plans ?? [];
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to load billing plan options"
    );
  }
};

const openCodeForm = (code?: RedeemCode) => {
  if (code) {
    editingCode.value = code;
    codeForm.value = {
      code: code.code,
      amount: code.amount,
      reward_type: code.reward_type ?? "credits",
      plan_id: code.plan_id ?? null,
      free_period_days: code.free_period_days ?? 30,
      discount_percent: Number(code.discount_percent ?? 0),
      discount_credits: Number(code.discount_credits ?? 0),
      coupon_scope: (code.coupon_scope as "initial" | "renewal" | "both" | null) ?? "initial",
      max_uses: code.max_uses,
      expires_at: code.expires_at ? code.expires_at.split(" ")[0] || "" : "",
    };
  } else {
    editingCode.value = null;
    codeForm.value = {
      code: "",
      amount: 0,
      reward_type: "credits",
      plan_id: null,
      free_period_days: 30,
      discount_percent: 0,
      discount_credits: 0,
      coupon_scope: "initial",
      max_uses: settings.value?.default_max_uses ?? 1,
      expires_at: "",
    };
  }
  showCodeForm.value = true;
};

const closeCodeForm = () => {
  showCodeForm.value = false;
  editingCode.value = null;
  codeForm.value = {
    code: "",
    amount: 0,
    reward_type: "credits",
    plan_id: null,
    free_period_days: 30,
    discount_percent: 0,
    discount_credits: 0,
    coupon_scope: "initial",
    max_uses: 1,
    expires_at: "",
  };
};

const saveCode = async () => {
  if (!codeForm.value.code.trim()) {
    toast.error("Code is required");
    return;
  }
  if (codeForm.value.amount < 0) {
    toast.error("Amount must be non-negative");
    return;
  }
  if (codeForm.value.reward_type === "billing_plan_trial") {
    if (!codeForm.value.plan_id) {
      toast.error("Please select a billing plan");
      return;
    }
    if (!codeForm.value.free_period_days || codeForm.value.free_period_days < 1) {
      toast.error("Free period must be at least 1 day");
      return;
    }
    codeForm.value.amount = 0;
    codeForm.value.discount_percent = 0;
    codeForm.value.discount_credits = 0;
  }
  if (codeForm.value.reward_type === "billing_plan_coupon") {
    if (codeForm.value.plan_id !== null && Number(codeForm.value.plan_id) < 1) {
      toast.error("Please select a valid plan or clear the plan target.");
      return;
    }
    if ((codeForm.value.discount_percent ?? 0) < 0 || (codeForm.value.discount_percent ?? 0) > 100) {
      toast.error("Discount percent must be between 0 and 100.");
      return;
    }
    if ((codeForm.value.discount_credits ?? 0) < 0) {
      toast.error("Discount credits must be non-negative.");
      return;
    }
    if ((codeForm.value.discount_percent ?? 0) <= 0 && (codeForm.value.discount_credits ?? 0) <= 0) {
      toast.error("Set either discount percent or discount credits.");
      return;
    }
    codeForm.value.amount = 0;
    codeForm.value.free_period_days = 30;
  }

  try {
    if (editingCode.value) {
      await updateCode(editingCode.value.id, codeForm.value);
      toast.success("Code updated successfully!");
    } else {
      await createCode(codeForm.value);
      toast.success("Code created successfully!");
    }
    closeCodeForm();
    await loadCodes(codesPage.value);
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to save code");
  }
};

const handleDeleteCode = async (code: RedeemCode) => {
  if (!confirm(`Are you sure you want to delete code "${code.code}"?`)) {
    return;
  }

  try {
    await deleteCode(code.id);
    toast.success("Code deleted successfully!");
    await loadCodes(codesPage.value);
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to delete code");
  }
};

const viewCodeUsage = async (code: RedeemCode) => {
  selectedCode.value = code;
  usagePage.value = 1;
  showUsage.value = true;
  await loadCodeUsage(code.id);
};

const loadCodeUsage = async (codeId: number, page: number = 1) => {
  usagePage.value = page;
  loadingUsage.value = true;
  try {
    const result = await getCodeUsage(codeId, 20, (page - 1) * 20);
    codeUsage.value = result.usage;
    usageTotal.value = result.total;
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to load code usage"
    );
  } finally {
    loadingUsage.value = false;
  }
};

const formatDate = (dateString: string | null): string => {
  if (!dateString) return "Never";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const isExpired = (code: RedeemCode): boolean => {
  if (!code.expires_at) return false;
  return new Date(code.expires_at) < new Date();
};

onMounted(() => {
  loadBillingPlanOptions();
  if (activeTab.value === "codes") {
    loadCodes();
  } else if (activeTab.value === "settings") {
    loadSettings();
  }
});
</script>

<template>
  <div class="w-full h-full overflow-auto p-4 md:p-8 min-h-screen">
    <div class="container mx-auto max-w-6xl">
      <div class="mb-6 text-center md:text-left">
        <h1
          class="text-3xl font-bold bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent"
        >
          Redeem Codes - Admin
        </h1>
        <p class="text-muted-foreground mt-2">
          Manage redemption codes and view usage statistics
        </p>
      </div>

      <Tabs v-model="activeTab" class="w-full">
        <TabsList class="grid w-full grid-cols-2 bg-muted/30 border border-border/50">
          <TabsTrigger value="codes">
            <Gift class="h-4 w-4 mr-2" />
            Codes
          </TabsTrigger>
          <TabsTrigger value="settings">
            <Settings class="h-4 w-4 mr-2" />
            Settings
          </TabsTrigger>
        </TabsList>

        <TabsContent value="codes" class="mt-4">
          <Card class="border-2 shadow-xl bg-card/50 backdrop-blur-sm">
            <div class="p-6">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Redemption Codes</h3>
                <div class="flex gap-2">
                  <Button
                    @click="loadCodes(codesPage)"
                    variant="outline"
                    size="sm"
                  >
                    Refresh
                  </Button>
                  <Button @click="openCodeForm()" size="sm">
                    <Plus class="h-4 w-4 mr-2" />
                    Create Code
                  </Button>
                </div>
              </div>

              <div
                v-if="loadingCodes && codes.length === 0"
                class="flex items-center justify-center py-12"
              >
                <Loader2 class="h-8 w-8 animate-spin" />
              </div>
              <div
                v-else-if="codes.length === 0"
                class="text-center py-12 text-muted-foreground"
              >
                No codes found. Create your first code!
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="code in codes"
                  :key="code.id"
                  class="flex items-center justify-between p-4 border rounded-lg hover:bg-accent transition-colors"
                >
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                      <Badge variant="outline" class="font-mono text-base">
                        {{ code.code }}
                      </Badge>
                      <Badge
                        :variant="code.is_valid ? 'default' : 'destructive'"
                      >
                        {{ code.is_valid ? "Valid" : "Invalid" }}
                      </Badge>
                      <Badge v-if="isExpired(code)" variant="secondary">
                        Expired
                      </Badge>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                      <div>
                        <span class="text-muted-foreground">Reward:</span>
                        <span class="ml-2 font-medium">
                          {{
                            code.reward_type === "billing_plan_trial"
                              ? `Plan trial (${code.free_period_days || 0}d)`
                              : code.reward_type === "billing_plan_coupon"
                                ? `Coupon (${code.coupon_scope || "initial"})`
                                : "Credits"
                          }}
                        </span>
                      </div>
                      <div>
                        <span class="text-muted-foreground">Amount:</span>
                        <span
                          class="ml-2 font-medium text-green-600 dark:text-green-400"
                        >
                          {{ code.amount_formatted || code.amount }} credits
                        </span>
                      </div>
                      <div>
                        <span class="text-muted-foreground">Uses:</span>
                        <span class="ml-2 font-medium">
                          {{ code.uses }} / {{ code.max_uses || "∞" }}
                        </span>
                      </div>
                      <div>
                        <span class="text-muted-foreground">Usage:</span>
                        <span class="ml-2 font-medium">
                          {{ code.usage_count || 0 }} users
                        </span>
                      </div>
                      <div>
                        <span class="text-muted-foreground">Expires:</span>
                        <span class="ml-2 font-medium">
                          {{ formatDate(code.expires_at) }}
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="flex gap-2 ml-4">
                    <Button
                      @click="viewCodeUsage(code)"
                      variant="outline"
                      size="sm"
                    >
                      <Users class="h-4 w-4" />
                    </Button>
                    <Button
                      @click="openCodeForm(code)"
                      variant="outline"
                      size="sm"
                    >
                      <Edit class="h-4 w-4" />
                    </Button>
                    <Button
                      @click="handleDeleteCode(code)"
                      variant="outline"
                      size="sm"
                    >
                      <Trash2 class="h-4 w-4 text-destructive" />
                    </Button>
                  </div>
                </div>
              </div>

              <!-- Pagination -->
              <div
                v-if="Math.ceil(codesTotal / 20) > 1"
                class="flex items-center justify-center gap-2 mt-6"
              >
                <Button
                  @click="loadCodes(codesPage - 1)"
                  :disabled="codesPage === 1"
                  variant="outline"
                  size="sm"
                >
                  <ChevronLeft class="h-4 w-4" />
                </Button>
                <span class="text-sm text-muted-foreground">
                  Page {{ codesPage }} of {{ Math.ceil(codesTotal / 20) }} ({{
                    codesTotal
                  }}
                  total)
                </span>
                <Button
                  @click="loadCodes(codesPage + 1)"
                  :disabled="codesPage >= Math.ceil(codesTotal / 20)"
                  variant="outline"
                  size="sm"
                >
                  <ChevronRight class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </Card>
        </TabsContent>

        <TabsContent value="settings" class="mt-4">
          <Card class="border-2 shadow-xl bg-card/50 backdrop-blur-sm">
            <div class="p-6">
              <div
                v-if="loading && !settings"
                class="flex items-center justify-center py-12"
              >
                <Loader2 class="h-8 w-8 animate-spin" />
              </div>
              <form
                v-else-if="settings"
                @submit.prevent="saveSettings"
                class="space-y-6"
              >
                <!-- Enable/Disable -->
                <div
                  class="flex items-center justify-between p-4 rounded-lg bg-muted/30 border border-border/50"
                >
                  <div>
                    <Label class="text-base font-semibold"
                      >Enable Redeem System</Label
                    >
                    <p class="text-sm text-muted-foreground">
                      Allow users to redeem codes for credits
                    </p>
                  </div>
                  <button
                    type="button"
                    role="switch"
                    :aria-checked="settings.is_enabled"
                    @click="settings.is_enabled = !settings.is_enabled"
                    :class="[
                      'relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background',
                      settings.is_enabled ? 'bg-primary' : 'bg-muted',
                    ]"
                  >
                    <span
                      class="pointer-events-none block h-5 w-5 rounded-full bg-white shadow-lg ring-0 transition-transform"
                      :class="
                        settings.is_enabled ? 'translate-x-5' : 'translate-x-0.5'
                      "
                    />
                  </button>
                </div>

                <!-- Allow Multiple Uses -->
                <div
                  class="flex items-center justify-between p-4 rounded-lg bg-muted/30 border border-border/50"
                >
                  <div>
                    <Label class="text-base font-semibold"
                      >Allow Multiple Uses</Label
                    >
                    <p class="text-sm text-muted-foreground">
                      Allow users to use the same code multiple times (if code
                      max_uses allows)
                    </p>
                  </div>
                  <button
                    type="button"
                    role="switch"
                    :aria-checked="settings.allow_multiple_uses"
                    @click="
                      settings.allow_multiple_uses =
                        !settings.allow_multiple_uses
                    "
                    :class="[
                      'relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background',
                      settings.allow_multiple_uses ? 'bg-primary' : 'bg-muted',
                    ]"
                  >
                    <span
                      class="pointer-events-none block h-5 w-5 rounded-full bg-white shadow-lg ring-0 transition-transform"
                      :class="
                        settings.allow_multiple_uses
                          ? 'translate-x-5'
                          : 'translate-x-0.5'
                      "
                    />
                  </button>
                </div>

                <!-- Default Max Uses -->
                <div>
                  <Label for="default_max_uses">Default Max Uses</Label>
                  <Input
                    id="default_max_uses"
                    v-model.number="settings.default_max_uses"
                    type="number"
                    min="0"
                    class="mt-2"
                  />
                  <p class="text-sm text-muted-foreground mt-1">
                    Default maximum uses for new codes (0 = unlimited)
                  </p>
                </div>

                <div class="flex justify-end pt-4 border-t">
                  <Button type="submit" :disabled="savingSettings">
                    <Loader2
                      v-if="savingSettings"
                      class="h-4 w-4 mr-2 animate-spin"
                    />
                    <Save v-else class="h-4 w-4 mr-2" />
                    Save Settings
                  </Button>
                </div>
              </form>
            </div>
          </Card>
        </TabsContent>
      </Tabs>

      <!-- Code Form Modal -->
      <div
        v-if="showCodeForm"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        @click.self="closeCodeForm"
      >
        <Card class="w-full max-w-md m-4 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-semibold">
                {{ editingCode ? "Edit Code" : "Create Code" }}
              </h3>
              <Button @click="closeCodeForm" variant="ghost" size="sm">
                <X class="h-4 w-4" />
              </Button>
            </div>

            <form @submit.prevent="saveCode" class="space-y-4">
              <div>
                <Label for="form-code">Code</Label>
                <Input
                  id="form-code"
                  v-model="codeForm.code"
                  type="text"
                  placeholder="REDEEM123"
                  class="mt-2 uppercase"
                  required
                />
              </div>

              <div>
                <Label for="form-reward-type">Reward Type</Label>
                <select
                  id="form-reward-type"
                  v-model="codeForm.reward_type"
                  class="mt-2 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                  <option value="credits">Credits</option>
                  <option value="billing_plan_trial">Billing plan trial</option>
                  <option value="billing_plan_coupon">Billing plan coupon</option>
                </select>
              </div>

              <div v-if="codeForm.reward_type === 'credits'">
                <Label for="form-amount">Amount (Credits)</Label>
                <Input
                  id="form-amount"
                  v-model.number="codeForm.amount"
                  type="number"
                  min="0"
                  class="mt-2"
                  required
                />
              </div>

              <div v-else-if="codeForm.reward_type === 'billing_plan_trial'" class="space-y-4">
                <div>
                  <Label for="form-plan-id">Billing Plan</Label>
                  <select
                    id="form-plan-id"
                    v-model.number="codeForm.plan_id"
                    class="mt-2 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    required
                  >
                    <option :value="null">Select a plan...</option>
                    <option v-for="plan in billingPlanOptions" :key="plan.id" :value="plan.id">
                      {{ plan.name }} ({{ plan.billing_period_days }}d cycle)
                    </option>
                  </select>
                </div>
                <div>
                  <Label for="form-free-period-days">Free Period (Days)</Label>
                  <Input
                    id="form-free-period-days"
                    v-model.number="codeForm.free_period_days"
                    type="number"
                    min="1"
                    class="mt-2"
                    required
                  />
                </div>
              </div>

              <div v-if="codeForm.reward_type === 'billing_plan_coupon'" class="space-y-4">
                <div>
                  <Label for="form-coupon-plan-id">Target Plan (optional)</Label>
                  <select
                    id="form-coupon-plan-id"
                    v-model.number="codeForm.plan_id"
                    class="mt-2 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  >
                    <option :value="null">Any billing plan</option>
                    <option v-for="plan in billingPlanOptions" :key="plan.id" :value="plan.id">
                      {{ plan.name }} ({{ plan.billing_period_days }}d cycle)
                    </option>
                  </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <Label for="form-discount-percent">Discount %</Label>
                    <Input
                      id="form-discount-percent"
                      v-model.number="codeForm.discount_percent"
                      type="number"
                      min="0"
                      max="100"
                      step="0.01"
                      class="mt-2"
                    />
                  </div>
                  <div>
                    <Label for="form-discount-credits">Discount Credits</Label>
                    <Input
                      id="form-discount-credits"
                      v-model.number="codeForm.discount_credits"
                      type="number"
                      min="0"
                      class="mt-2"
                    />
                  </div>
                </div>
                <div>
                  <Label for="form-coupon-scope">Applies To</Label>
                  <select
                    id="form-coupon-scope"
                    v-model="codeForm.coupon_scope"
                    class="mt-2 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  >
                    <option value="initial">First purchase only</option>
                    <option value="renewal">Renewals only</option>
                    <option value="both">First purchase and renewals</option>
                  </select>
                </div>
              </div>

              <div>
                <Label for="form-max-uses">Max Uses</Label>
                <Input
                  id="form-max-uses"
                  v-model.number="codeForm.max_uses"
                  type="number"
                  min="0"
                  class="mt-2"
                  required
                />
                <p class="text-sm text-muted-foreground mt-1">
                  0 = unlimited uses
                </p>
              </div>

              <div>
                <Label for="form-expires">Expires At (Optional)</Label>
                <Input
                  id="form-expires"
                  v-model="codeForm.expires_at"
                  type="date"
                  class="mt-2"
                />
              </div>

              <div class="flex justify-end gap-2 pt-4">
                <Button type="button" @click="closeCodeForm" variant="outline">
                  Cancel
                </Button>
                <Button type="submit">
                  <Save class="h-4 w-4 mr-2" />
                  {{ editingCode ? "Update" : "Create" }}
                </Button>
              </div>
            </form>
          </div>
        </Card>
      </div>

      <!-- Code Usage Modal -->
      <div
        v-if="showUsage && selectedCode"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        @click.self="showUsage = false"
      >
        <Card class="w-full max-w-2xl m-4 max-h-[80vh] overflow-auto border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="p-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-lg font-semibold">Code Usage</h3>
                <p class="text-sm text-muted-foreground">
                  {{ selectedCode.code }}
                </p>
              </div>
              <Button @click="showUsage = false" variant="ghost" size="sm">
                <X class="h-4 w-4" />
              </Button>
            </div>

            <div
              v-if="loadingUsage && codeUsage.length === 0"
              class="flex items-center justify-center py-12"
            >
              <Loader2 class="h-8 w-8 animate-spin" />
            </div>
            <div
              v-else-if="codeUsage.length === 0"
              class="text-center py-12 text-muted-foreground"
            >
              No usage found for this code
            </div>
            <div v-else class="space-y-2">
              <div
                v-for="usage in codeUsage"
                :key="usage.id"
                class="flex items-center justify-between p-4 border rounded-lg"
              >
                <div>
                  <div class="font-medium">
                    {{
                      usage.username || usage.email || `User #${usage.user_id}`
                    }}
                  </div>
                  <div class="text-sm text-muted-foreground">
                    {{ formatDate(usage.used_at) }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Pagination -->
            <div
              v-if="Math.ceil(usageTotal / 20) > 1"
              class="flex items-center justify-center gap-2 mt-6"
            >
              <Button
                @click="loadCodeUsage(selectedCode.id, usagePage - 1)"
                :disabled="usagePage === 1"
                variant="outline"
                size="sm"
              >
                <ChevronLeft class="h-4 w-4" />
              </Button>
              <span class="text-sm text-muted-foreground">
                Page {{ usagePage }} of {{ Math.ceil(usageTotal / 20) }} ({{
                  usageTotal
                }}
                total)
              </span>
              <Button
                @click="loadCodeUsage(selectedCode.id, usagePage + 1)"
                :disabled="usagePage >= Math.ceil(usageTotal / 20)"
                variant="outline"
                size="sm"
              >
                <ChevronRight class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </Card>
      </div>
    </div>
  </div>
</template>
